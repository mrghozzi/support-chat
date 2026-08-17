<?php

declare(strict_types=1);

namespace MyAds\Plugins\SupportChat;

use App\Models\Option;

class SupportChatSettings
{
    public const OPTION_TYPE = 'plugin_support_chat_settings';
    public const OPTION_NAME = 'support-chat';
    public const DEFAULTS = [
        'channel_mode' => 'local',
        'widget_enabled' => 1,
        'welcome_message' => 'We usually reply within a few minutes.',
        'team_name' => 'MYADS Support',
        'whatsapp_number' => '',
        'whatsapp_prefill' => 'Hello, I need help with MYADS.',
        'messenger_url' => '',
        'visibility_rules' => '',
        'ai_enabled' => 1,
        'ai_mode' => 'auto_reply',
        'ai_provider' => 'pollinations',
        'ai_bot_name' => 'مساعد MYADS الذكي',
        'pollinations_api_key' => '',
        'pollinations_model' => 'openai',
        'groq_api_key' => '',
        'groq_model' => 'llama-3.3-70b-versatile',
        'gemini_api_key' => '',
        'gemini_model' => 'gemini-1.5-flash',
        'openai_api_key' => '',
        'openai_model' => 'gpt-4o-mini',
        'ai_system_prompt' => '',
        'ai_temperature' => 0.7,
        'ai_max_tokens' => 1024,
    ];

    public static function pollinationsModelOptions(): array
    {
        return [
            'OpenAI Models' => [
                'openai' => 'OpenAI GPT-5.4 Nano (Fast, affordable all-rounder - Recommended)',
                'openai-fast' => 'GPT-5 Nano (Ultra-fast and low-cost)',
                'gpt-5.4-mini' => 'OpenAI GPT-5.4 Mini (Balanced quality & speed)',
                'gpt-5.4' => 'OpenAI GPT-5.4 (Deep reasoning)',
                'openai-large' => 'OpenAI GPT-5.5 (Frontier reasoning)',
            ],
            'Anthropic Claude' => [
                'claude-sonnet-5' => 'Claude Sonnet 5 (Sharp reasoning & fast responses)',
                'claude-fast' => 'Claude Haiku 4.5 (Quick, capable chat at low cost)',
                'claude' => 'Claude Sonnet 4.6 (Excellent writing & analysis)',
                'claude-large' => 'Claude Opus 5 (Frontier reasoning)',
            ],
            'Google Gemini' => [
                'gemini' => 'Google Gemini 3.7 Flash (Fast multimodal reasoning & web search)',
                'gemini-fast' => 'Google Gemini 2.5 Flash Lite (Ultra-cheap everyday model)',
                'gemini-3-flash' => 'Google Gemini 3 Flash Preview (Pro-grade speed)',
                'gemini-large' => 'Google Gemini 3.1 Pro Preview (Top-tier reasoning)',
            ],
            'DeepSeek' => [
                'deepseek' => 'DeepSeek V4 Flash (Fast reasoning & coding)',
                'deepseek-pro' => 'DeepSeek V4 Pro (Deep reasoning & complex tasks)',
            ],
            'Meta Llama' => [
                'llama' => 'Meta Llama 3.3 70B (Open-source workhorse for general chat)',
                'llama-maverick' => 'Meta Llama 4 Maverick (Multimodal long-context)',
                'llama-scout' => 'Meta Llama 4 Scout (Long-context specialist)',
            ],
            'Mistral & Qwen' => [
                'mistral' => 'Mistral Small 4 (Compact all-rounder)',
                'mistral-large' => 'Mistral Large 3 (Polished multilingual writing)',
                'qwen-large' => 'Qwen 3.7 Plus (Multimodal agent intelligence)',
                'qwen3.7-max' => 'Qwen 3.7 Max (Flagship long-context model)',
                'qwen-coder' => 'Qwen 3 Coder 30B (Specialized for code & tech support)',
            ],
            'Search & Special Models' => [
                'perplexity' => 'Perplexity Sonar Pro (Live web grounded search with citations)',
                'perplexity-fast' => 'Perplexity Sonar Fast Search (Quick web search answers)',
                'grok' => 'xAI Grok 4.20 (Multimodal chat & reasoning)',
                'minimax' => 'MiniMax M3 (Strong coding & long memory)',
            ],
        ];
    }

    public static function all(): array
    {
        $settings = self::DEFAULTS;

        try {
            $row = Option::query()
                ->where('o_type', self::OPTION_TYPE)
                ->where('name', self::OPTION_NAME)
                ->first();
        } catch (\Throwable) {
            return self::normalize($settings);
        }

        if (!$row) {
            return self::normalize($settings);
        }

        $decoded = json_decode((string) $row->o_valuer, true);
        if (!is_array($decoded)) {
            return self::normalize($settings);
        }

        return self::normalize(array_merge($settings, $decoded));
    }

    public static function get(string $key, mixed $fallback = null): mixed
    {
        $settings = self::all();

        return $settings[$key] ?? $fallback;
    }

    public static function save(array $values): void
    {
        $current = self::all();
        $normalized = self::normalizeIncoming(array_merge($current, $values));

        Option::query()->updateOrCreate(
            ['o_type' => self::OPTION_TYPE, 'name' => self::OPTION_NAME],
            [
                'o_valuer' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'o_parent' => 0,
                'o_order' => 0,
                'o_mode' => '',
            ]
        );
    }

    public static function normalizeIncoming(array $values): array
    {
        return self::normalize([
            'channel_mode' => (string) ($values['channel_mode'] ?? self::DEFAULTS['channel_mode']),
            'widget_enabled' => !empty($values['widget_enabled']) ? 1 : 0,
            'welcome_message' => trim((string) ($values['welcome_message'] ?? self::DEFAULTS['welcome_message'])),
            'team_name' => trim((string) ($values['team_name'] ?? self::DEFAULTS['team_name'])),
            'whatsapp_number' => preg_replace('/\D+/', '', (string) ($values['whatsapp_number'] ?? '')) ?? '',
            'whatsapp_prefill' => trim((string) ($values['whatsapp_prefill'] ?? self::DEFAULTS['whatsapp_prefill'])),
            'messenger_url' => trim((string) ($values['messenger_url'] ?? '')),
            'visibility_rules' => self::sanitizeVisibilityRules((string) ($values['visibility_rules'] ?? '')),
            'ai_enabled' => !empty($values['ai_enabled']) ? 1 : 0,
            'ai_mode' => (string) ($values['ai_mode'] ?? self::DEFAULTS['ai_mode']),
            'ai_provider' => (string) ($values['ai_provider'] ?? self::DEFAULTS['ai_provider']),
            'ai_bot_name' => trim((string) ($values['ai_bot_name'] ?? self::DEFAULTS['ai_bot_name'])),
            'pollinations_api_key' => trim((string) ($values['pollinations_api_key'] ?? '')),
            'pollinations_model' => trim((string) ($values['pollinations_model'] ?? self::DEFAULTS['pollinations_model'])),
            'groq_api_key' => trim((string) ($values['groq_api_key'] ?? '')),
            'groq_model' => trim((string) ($values['groq_model'] ?? self::DEFAULTS['groq_model'])),
            'gemini_api_key' => trim((string) ($values['gemini_api_key'] ?? '')),
            'gemini_model' => trim((string) ($values['gemini_model'] ?? self::DEFAULTS['gemini_model'])),
            'openai_api_key' => trim((string) ($values['openai_api_key'] ?? '')),
            'openai_model' => trim((string) ($values['openai_model'] ?? self::DEFAULTS['openai_model'])),
            'ai_system_prompt' => trim((string) ($values['ai_system_prompt'] ?? '')),
            'ai_temperature' => (float) ($values['ai_temperature'] ?? self::DEFAULTS['ai_temperature']),
            'ai_max_tokens' => (int) ($values['ai_max_tokens'] ?? self::DEFAULTS['ai_max_tokens']),
        ]);
    }

    public static function visibilityRules(): array
    {
        $lines = preg_split('/[\r\n]+/', (string) self::get('visibility_rules', '')) ?: [];

        return array_values(array_unique(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $lines
        ))));
    }

    private static function normalize(array $settings): array
    {
        $channelMode = in_array(($settings['channel_mode'] ?? 'local'), ['local', 'whatsapp', 'messenger'], true)
            ? (string) $settings['channel_mode']
            : 'local';

        $aiMode = in_array(($settings['ai_mode'] ?? 'auto_reply'), ['auto_reply', 'assist_admin', 'always_ai', 'off'], true)
            ? (string) $settings['ai_mode']
            : 'auto_reply';

        $aiProvider = in_array(($settings['ai_provider'] ?? 'pollinations'), ['pollinations', 'groq', 'gemini', 'openai'], true)
            ? (string) $settings['ai_provider']
            : 'pollinations';

        return [
            'channel_mode' => $channelMode,
            'widget_enabled' => !empty($settings['widget_enabled']) ? 1 : 0,
            'welcome_message' => trim((string) ($settings['welcome_message'] ?? self::DEFAULTS['welcome_message'])),
            'team_name' => trim((string) ($settings['team_name'] ?? self::DEFAULTS['team_name'])),
            'whatsapp_number' => preg_replace('/\D+/', '', (string) ($settings['whatsapp_number'] ?? '')) ?? '',
            'whatsapp_prefill' => trim((string) ($settings['whatsapp_prefill'] ?? self::DEFAULTS['whatsapp_prefill'])),
            'messenger_url' => trim((string) ($settings['messenger_url'] ?? '')),
            'visibility_rules' => self::sanitizeVisibilityRules((string) ($settings['visibility_rules'] ?? '')),
            'ai_enabled' => !empty($settings['ai_enabled']) ? 1 : 0,
            'ai_mode' => $aiMode,
            'ai_provider' => $aiProvider,
            'ai_bot_name' => trim((string) ($settings['ai_bot_name'] ?? self::DEFAULTS['ai_bot_name'])),
            'pollinations_api_key' => trim((string) ($settings['pollinations_api_key'] ?? '')),
            'pollinations_model' => trim((string) ($settings['pollinations_model'] ?? self::DEFAULTS['pollinations_model'])),
            'groq_api_key' => trim((string) ($settings['groq_api_key'] ?? '')),
            'groq_model' => trim((string) ($settings['groq_model'] ?? self::DEFAULTS['groq_model'])),
            'gemini_api_key' => trim((string) ($settings['gemini_api_key'] ?? '')),
            'gemini_model' => trim((string) ($settings['gemini_model'] ?? self::DEFAULTS['gemini_model'])),
            'openai_api_key' => trim((string) ($settings['openai_api_key'] ?? '')),
            'openai_model' => trim((string) ($settings['openai_model'] ?? self::DEFAULTS['openai_model'])),
            'ai_system_prompt' => trim((string) ($settings['ai_system_prompt'] ?? '')),
            'ai_temperature' => (float) ($settings['ai_temperature'] ?? self::DEFAULTS['ai_temperature']),
            'ai_max_tokens' => (int) ($settings['ai_max_tokens'] ?? self::DEFAULTS['ai_max_tokens']),
        ];
    }

    private static function sanitizeVisibilityRules(string $rules): string
    {
        $lines = preg_split('/[\r\n]+/', $rules) ?: [];
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $lines
        ))));

        return implode(PHP_EOL, $normalized);
    }
}
