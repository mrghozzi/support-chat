<?php

declare(strict_types=1);

namespace MyAds\Plugins\SupportChat\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MyAds\Plugins\SupportChat\Models\SupportChatMessage;
use MyAds\Plugins\SupportChat\Models\SupportChatThread;
use MyAds\Plugins\SupportChat\SupportChatSettings;

class SupportChatAiService
{
    private const HTTP_TIMEOUT = 18;

    /**
     * Generate an AI response or draft suggestion for a support thread.
     */
    public function generateReply(SupportChatThread $thread, ?string $customInstruction = null, bool $isAdminCoPilot = false): ?string
    {
        $settings = SupportChatSettings::all();
        $provider = (string) ($settings['ai_provider'] ?? 'pollinations');

        $messages = $this->buildConversationPayload($thread, $settings, $customInstruction, $isAdminCoPilot);
        if (empty($messages)) {
            return $this->getSmartFallbackReply($thread, $isAdminCoPilot);
        }

        try {
            $reply = match ($provider) {
                'groq' => $this->callGroq($messages, $settings),
                'gemini' => $this->callGemini($messages, $settings),
                'openai' => $this->callOpenAi($messages, $settings),
                default => $this->callPollinations($messages, $settings),
            };

            if (!empty($reply) && !str_starts_with($reply, '{"error":') && !str_contains($reply, 'Queue full')) {
                return trim($reply);
            }

            // If primary provider failed or was empty, try secondary fallbacks
            if ($provider !== 'pollinations') {
                Log::info("[SupportChatAi] Provider '{$provider}' failed. Trying Pollinations fallback.");
                $fallbackReply = $this->callPollinations($messages, $settings);
                if (!empty($fallbackReply) && !str_contains($fallbackReply, 'Queue full')) {
                    return trim($fallbackReply);
                }
            }
        } catch (\Throwable $e) {
            Log::error("[SupportChatAi] Exception during AI reply generation ({$provider}): " . $e->getMessage());

            // Try fallback
            if ($provider !== 'pollinations') {
                try {
                    $fallbackReply = $this->callPollinations($messages, $settings);
                    if (!empty($fallbackReply) && !str_contains($fallbackReply, 'Queue full')) {
                        return trim($fallbackReply);
                    }
                } catch (\Throwable) {
                }
            }
        }

        // Return intelligent contextual fallback if all external calls were queued or offline
        return $this->getSmartFallbackReply($thread, $isAdminCoPilot);
    }

    /**
     * Test connection to a specific AI provider.
     */
    public function testConnection(string $provider, ?string $apiKey = null, ?string $model = null): array
    {
        $settings = SupportChatSettings::all();
        $apiKey = trim((string) ($apiKey ?? ''));
        $model = trim((string) ($model ?? ''));

        $testMessages = [
            [
                'role' => 'system',
                'content' => 'You are an AI assistant for MYADS platform. Respond concisely with: "Connection successful! AI support service is active and operational."',
            ],
            [
                'role' => 'user',
                'content' => 'Ping test.',
            ],
        ];

        try {
            $response = match ($provider) {
                'groq' => $this->callGroqRaw($testMessages, $apiKey ?: (string) ($settings['groq_api_key'] ?? ''), $model ?: (string) ($settings['groq_model'] ?? 'llama-3.3-70b-versatile')),
                'gemini' => $this->callGeminiRaw($testMessages, $apiKey ?: (string) ($settings['gemini_api_key'] ?? ''), $model ?: (string) ($settings['gemini_model'] ?? 'gemini-1.5-flash')),
                'openai' => $this->callOpenAiRaw($testMessages, $apiKey ?: (string) ($settings['openai_api_key'] ?? ''), $model ?: (string) ($settings['openai_model'] ?? 'gpt-4o-mini')),
                default => $this->callPollinationsRaw($testMessages, $apiKey ?: (string) ($settings['pollinations_api_key'] ?? ''), $model ?: (string) ($settings['pollinations_model'] ?? 'openai')),
            };

            if (!empty($response) && !str_contains($response, 'Queue full') && !str_contains($response, '429')) {
                return [
                    'success' => true,
                    'provider' => $provider,
                    'message' => __('support_chat::messages.ai_test_success'),
                    'sample_reply' => Str::limit(trim($response), 300),
                ];
            }

            return [
                'success' => false,
                'provider' => $provider,
                'message' => __('support_chat::messages.ai_test_failed_empty'),
                'sample_reply' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'provider' => $provider,
                'message' => $e->getMessage(),
                'sample_reply' => null,
            ];
        }
    }

    private function buildConversationPayload(SupportChatThread $thread, array $settings, ?string $customInstruction = null, bool $isAdminCoPilot = false): array
    {
        $siteName = (string) (config('app.name') ?: 'MYADS');
        $botName = (string) ($settings['ai_bot_name'] ?? 'MYADS AI Assistant');
        $customPrompt = trim((string) ($settings['ai_system_prompt'] ?? ''));

        $visitorName = $thread->participantName();
        $pageTitle = (string) ($thread->started_page_title ?: 'Home Page');
        $pageUrl = (string) ($thread->started_page_url ?: '');

        $systemPrompt = "You are '{$botName}', the official intelligent technical support assistant for {$siteName} platform (digital advertising, marketplace, forum, and publisher community).\n";
        $systemPrompt .= "Current visitor/user name: {$visitorName}\n";
        if (!empty($pageTitle) && $pageTitle !== 'Unknown Page') {
            $systemPrompt .= "Browsing context: {$pageTitle} ({$pageUrl})\n\n";
        }

        if (!empty($customPrompt)) {
            $systemPrompt .= "### Administrator Custom Guidelines:\n{$customPrompt}\n\n";
        } else {
            $systemPrompt .= "### Response Guidelines:\n";
            $systemPrompt .= "1. Respond politely, professionally, concisely, and directly. Always reply in the user's preferred language (match the language of the inquiry).\n";
            $systemPrompt .= "2. Provide clear, accurate help regarding platform services, ad exchange, PTS points, and marketplace features.\n";
            $systemPrompt .= "3. If the request requires administrative intervention or manual review, inform the user that the support team will follow up shortly.\n";
        }

        if ($isAdminCoPilot) {
            $systemPrompt .= "\n[Admin Co-Pilot Mode]: Draft a ready-to-send, professional support message for the administrator to review and send directly to the customer.\n";
        }

        if (!empty($customInstruction)) {
            $systemPrompt .= "\n[Admin Guidance]: {$customInstruction}\n";
        }

        $payload = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Fetch recent messages
        $recentMessages = $thread->messages()
            ->orderBy('id', 'desc')
            ->limit(8)
            ->get()
            ->reverse();

        foreach ($recentMessages as $msg) {
            /** @var SupportChatMessage $msg */
            $role = in_array($msg->sender_type, ['admin', 'ai', 'bot'], true) ? 'assistant' : 'user';
            $payload[] = [
                'role' => $role,
                'content' => (string) $msg->body,
            ];
        }

        return $payload;
    }

    private function callPollinations(array $messages, array $settings): ?string
    {
        $apiKey = trim((string) ($settings['pollinations_api_key'] ?? ''));
        $model = trim((string) ($settings['pollinations_model'] ?? 'openai'));
        if (empty($model) || str_contains($model, 'safety')) {
            $model = 'openai';
        }

        return $this->callPollinationsRaw($messages, $apiKey, $model);
    }

    private function callPollinationsRaw(array $messages, string $apiKey = '', string $model = 'openai'): ?string
    {
        $model = (!empty($model) && !str_contains($model, 'safety')) ? $model : 'openai';

        // Extract last user message and system prompt for fast request
        $userMessage = '';
        $systemInstruction = '';
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'user') {
                $userMessage = (string) ($m['content'] ?? '');
            } elseif (($m['role'] ?? '') === 'system') {
                $systemInstruction = (string) ($m['content'] ?? '');
            }
        }

        // 1. If no API key, attempt fast GET with concise prompt first (much faster on Pollinations text API)
        if (empty($apiKey) && !empty($userMessage)) {
            try {
                $encodedPrompt = rawurlencode($userMessage);
                $url = "https://text.pollinations.ai/{$encodedPrompt}?model=" . rawurlencode($model);
                if (!empty($systemInstruction)) {
                    $url .= "&system=" . rawurlencode(Str::limit($systemInstruction, 180));
                }

                $getRes = Http::withoutVerifying()->timeout(5)->get($url);
                if ($getRes->successful()) {
                    $text = trim((string) $getRes->body());
                    if (!empty($text) && !str_contains($text, 'Queue full') && !str_contains($text, '429') && !str_contains($text, 'error')) {
                        return $text;
                    }
                }
            } catch (\Throwable $e) {
                Log::info('[SupportChatAi] Pollinations fast GET bypassed: ' . $e->getMessage());
            }
        }

        // 2. Attempt POST request with JSON messages payload
        try {
            $request = Http::withoutVerifying()
                ->timeout(6)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json, text/plain',
                ]);

            if (!empty($apiKey)) {
                $request = $request->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ]);
            }

            $response = $request->post('https://text.pollinations.ai/', [
                'messages' => $messages,
                'model' => $model,
                'seed' => rand(1000, 999999),
            ]);

            if ($response->successful()) {
                $body = (string) $response->body();
                $decoded = json_decode($body, true);
                if (is_array($decoded) && isset($decoded['choices'][0]['message']['content'])) {
                    return trim((string) $decoded['choices'][0]['message']['content']);
                }

                if (!empty($body) && !str_contains($body, 'Queue full') && !str_contains($body, 'error') && !str_contains($body, '429')) {
                    return trim($body);
                }
            }
        } catch (\Throwable $e) {
            Log::info('[SupportChatAi] Pollinations POST attempt failed: ' . $e->getMessage());
        }

        return null;
    }

    private function callGroq(array $messages, array $settings): ?string
    {
        $apiKey = trim((string) ($settings['groq_api_key'] ?? ''));
        if (empty($apiKey)) {
            return null;
        }

        $model = trim((string) ($settings['groq_model'] ?? 'llama-3.3-70b-versatile')) ?: 'llama-3.3-70b-versatile';

        return $this->callGroqRaw($messages, $apiKey, $model);
    }

    private function callGroqRaw(array $messages, string $apiKey, string $model): ?string
    {
        if (empty($apiKey)) {
            throw new \InvalidArgumentException(__('support_chat::messages.api_key_required'));
        }

        $response = Http::withoutVerifying()
            ->timeout(self::HTTP_TIMEOUT)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $model ?: 'llama-3.3-70b-versatile',
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1024,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        }

        $errorMsg = $response->json('error.message') ?: ('Groq API Error (' . $response->status() . ')');
        Log::error('[SupportChatAi] Groq Error: ' . $errorMsg);
        throw new \RuntimeException($errorMsg);
    }

    private function callGemini(array $messages, array $settings): ?string
    {
        $apiKey = trim((string) ($settings['gemini_api_key'] ?? ''));
        if (empty($apiKey)) {
            return null;
        }

        $model = trim((string) ($settings['gemini_model'] ?? 'gemini-1.5-flash')) ?: 'gemini-1.5-flash';

        return $this->callGeminiRaw($messages, $apiKey, $model);
    }

    private function callGeminiRaw(array $messages, string $apiKey, string $model): ?string
    {
        if (empty($apiKey)) {
            throw new \InvalidArgumentException(__('support_chat::messages.api_key_required'));
        }

        $systemInstructionText = '';
        $rawGeminiContents = [];

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = trim((string) ($msg['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            if ($role === 'system') {
                $systemInstructionText .= ($systemInstructionText ? "\n\n" : '') . $content;
            } elseif ($role === 'assistant') {
                $rawGeminiContents[] = [
                    'role' => 'model',
                    'text' => $content,
                ];
            } else {
                $rawGeminiContents[] = [
                    'role' => 'user',
                    'text' => $content,
                ];
            }
        }

        // Merge consecutive turns with the same role to strictly alternate user/model
        $geminiContents = [];
        foreach ($rawGeminiContents as $item) {
            $lastIndex = count($geminiContents) - 1;
            if ($lastIndex >= 0 && $geminiContents[$lastIndex]['role'] === $item['role']) {
                $geminiContents[$lastIndex]['parts'][0]['text'] .= "\n\n" . $item['text'];
            } else {
                $geminiContents[] = [
                    'role' => $item['role'],
                    'parts' => [['text' => $item['text']]],
                ];
            }
        }

        if (empty($geminiContents)) {
            $geminiContents[] = [
                'role' => 'user',
                'parts' => [['text' => 'Hello']],
            ];
        }

        $payload = [
            'contents' => $geminiContents,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024,
            ],
        ];

        if (!empty($systemInstructionText)) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstructionText],
                ],
            ];
        }

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::withoutVerifying()
            ->timeout(self::HTTP_TIMEOUT)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, $payload);

        if ($response->successful()) {
            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            return trim((string) $text);
        }

        $errorMsg = $response->json('error.message') ?: ('Gemini API Error (' . $response->status() . ')');
        Log::error('[SupportChatAi] Gemini Error: ' . $errorMsg);
        throw new \RuntimeException($errorMsg);
    }

    private function callOpenAi(array $messages, array $settings): ?string
    {
        $apiKey = trim((string) ($settings['openai_api_key'] ?? ''));
        if (empty($apiKey)) {
            return null;
        }

        $model = trim((string) ($settings['openai_model'] ?? 'gpt-4o-mini')) ?: 'gpt-4o-mini';

        return $this->callOpenAiRaw($messages, $apiKey, $model);
    }

    private function callOpenAiRaw(array $messages, string $apiKey, string $model): ?string
    {
        if (empty($apiKey)) {
            throw new \InvalidArgumentException(__('support_chat::messages.api_key_required'));
        }

        $response = Http::withoutVerifying()
            ->timeout(self::HTTP_TIMEOUT)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model ?: 'gpt-4o-mini',
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1024,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        }

        $errorMsg = $response->json('error.message') ?: ('OpenAI API Error (' . $response->status() . ')');
        Log::error('[SupportChatAi] OpenAI Error: ' . $errorMsg);
        throw new \RuntimeException($errorMsg);
    }

    /**
     * Fallback reply when all remote AI providers are unreachable or queued.
     */
    private function getSmartFallbackReply(SupportChatThread $thread, bool $isAdminCoPilot): string
    {
        $visitor = $thread->participantName();
        $siteName = (string) (config('app.name') ?: 'MYADS');

        // Extract last user message to make the fallback contextual and helpful
        $lastUserMsg = $thread->messages()
            ->whereIn('sender_type', ['guest', 'member'])
            ->latest('id')
            ->value('body');

        $topic = '';
        if (!empty($lastUserMsg)) {
            $cleaned = trim(preg_replace('/\s+/', ' ', (string) $lastUserMsg));
            $topic = ' regarding: "' . Str::limit($cleaned, 75) . '", ';
        }

        if ($isAdminCoPilot) {
            return "Hello {$visitor}, thank you for contacting {$siteName} support.{$topic}We are glad to help, and our support team will follow up on your request with the necessary details shortly.";
        }

        return "Hello {$visitor}! Thank you for reaching out to {$siteName} support.{$topic}Your message has been received, and our team will follow up to assist you as soon as possible.";
    }
}
