<?php

declare(strict_types=1);

namespace MyAds\Plugins\SupportChat\Services;

use App\Models\User;
use App\Services\SecurityPolicyService;
use App\Services\SecurityThrottleService;
use App\Services\V420SchemaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use MyAds\Plugins\SupportChat\Models\SupportChatMessage;
use MyAds\Plugins\SupportChat\Models\SupportChatThread;
use MyAds\Plugins\SupportChat\SupportChatSettings;
use Symfony\Component\HttpFoundation\Cookie;

class SupportChatService
{
    public const COOKIE_NAME = 'myads_support_chat';
    private const GUEST_COOLDOWN_SECONDS = 6;
    private const THREAD_PAGE_SIZE = 16;
    private const THREAD_MESSAGE_LIMIT = 40;

    public function __construct(
        private readonly SupportChatSchema $schema,
        private readonly SecurityPolicyService $securityPolicy,
        private readonly SecurityThrottleService $securityThrottle,
        private readonly V420SchemaService $coreSchema,
        private readonly SupportChatAiService $aiService
    ) {
    }

    public function aiService(): SupportChatAiService
    {
        return $this->aiService;
    }

    public function settings(): array
    {
        return SupportChatSettings::all();
    }

    public function dashboardStats(): array
    {
        $settings = $this->settings();
        $totalThreads = 0;
        $openThreads = 0;
        $awaitingReplyThreads = 0;
        $closedThreads = 0;
        $aiMessagesCount = 0;

        if ($this->schemaReady()) {
            try {
                $totalThreads = SupportChatThread::query()->count();
                $openThreads = SupportChatThread::query()->where('status', 'open')->count();
                $awaitingReplyThreads = SupportChatThread::query()->awaitingReply()->count();
                $closedThreads = SupportChatThread::query()->where('status', 'closed')->count();
                $aiMessagesCount = SupportChatMessage::query()->whereIn('sender_type', ['ai', 'bot'])->count();
            } catch (\Throwable) {
            }
        }

        return [
            'total_threads' => $totalThreads,
            'open_threads' => $openThreads,
            'awaiting_reply' => $awaitingReplyThreads,
            'closed_threads' => $closedThreads,
            'ai_messages_count' => $aiMessagesCount,
            'channel_mode' => (string) ($settings['channel_mode'] ?? 'local'),
            'ai_provider' => (string) ($settings['ai_provider'] ?? 'pollinations'),
            'ai_enabled' => !empty($settings['ai_enabled']),
            'ai_mode' => (string) ($settings['ai_mode'] ?? 'auto_reply'),
        ];
    }

    public function schemaReady(): bool
    {
        return $this->schema->supportsStorage();
    }

    public function widgetPayload(Request $request): array
    {
        $settings = $this->settings();
        $channelMode = $settings['channel_mode'] ?? 'local';
        $schemaReady = $this->schemaReady();
        $externalUrl = $this->externalUrl($settings);
        $shouldRender = (int) ($settings['widget_enabled'] ?? 0) === 1
            && !$this->isExcludedRequest($request)
            && match ($channelMode) {
                'local' => $schemaReady,
                'whatsapp', 'messenger' => $externalUrl !== null,
                default => false,
            };

        return [
            'settings' => $settings,
            'channel_mode' => $channelMode,
            'schema_ready' => $schemaReady,
            'should_render' => $shouldRender,
            'thread_url' => route('support_chat.thread.current'),
            'poll_url' => route('support_chat.thread.poll'),
            'start_url' => route('support_chat.thread.start'),
            'message_url' => route('support_chat.thread.message'),
            'external_url' => $externalUrl,
            'external_label' => $this->externalLabel($settings),
            'setup_notice' => $this->schema->notice(),
        ];
    }

    public function notice(): ?array
    {
        return $this->schema->notice();
    }

    public function adminThreads(string $filter = 'open', string $search = ''): LengthAwarePaginator
    {
        if (!$this->schemaReady()) {
            return new LengthAwarePaginator([], 0, self::THREAD_PAGE_SIZE, max(1, request()->integer('page', 1)), [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        $query = SupportChatThread::query()
            ->with([
                'member:id,username,email,img',
                'assignedAdmin:id,username,email,img',
                'latestMessage',
            ])
            ->withCount([
                'messages as admin_unread_count' => static function (Builder $builder): void {
                    $builder->whereNull('seen_at')->whereIn('sender_type', ['guest', 'member']);
                },
                'messages as public_unread_count' => static function (Builder $builder): void {
                    $builder->whereNull('seen_at')->where('sender_type', 'admin');
                },
            ]);

        $search = trim($search);
        if ($search !== '') {
            $query->where(static function (Builder $builder) use ($search): void {
                $builder
                    ->where('visitor_name', 'like', '%' . $search . '%')
                    ->orWhere('visitor_email', 'like', '%' . $search . '%')
                    ->orWhere('started_page_title', 'like', '%' . $search . '%')
                    ->orWhereHas('member', static function (Builder $memberQuery) use ($search): void {
                        $memberQuery
                            ->where('username', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        match ($filter) {
            'all' => $query,
            'awaiting_reply' => $query->awaitingReply(),
            'closed' => $query->where('status', 'closed'),
            default => $query->where('status', '!=', 'closed'),
        };

        try {
            return $query
                ->orderByDesc('support_chat_threads.last_message_at')
                ->orderByDesc('support_chat_threads.id')
                ->paginate(self::THREAD_PAGE_SIZE)
                ->withQueryString();
        } catch (\Throwable $e) {
            logger()->error('[SupportChat] Admin threads query failed: ' . $e->getMessage(), [
                'filter' => $filter,
                'search' => $search,
                'trace' => $e->getTraceAsString(),
            ]);

            return new LengthAwarePaginator([], 0, self::THREAD_PAGE_SIZE, max(1, request()->integer('page', 1)), [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }
    }

    public function findThreadOrFail(int $threadId): SupportChatThread
    {
        if (!$this->schemaReady()) {
            throw new ModelNotFoundException();
        }

        try {
            return SupportChatThread::query()
                ->with([
                    'member:id,username,email,img',
                    'assignedAdmin:id,username,email,img',
                ])
                ->withCount([
                    'messages as admin_unread_count' => static function (Builder $builder): void {
                        $builder->whereNull('seen_at')->whereIn('sender_type', ['guest', 'member']);
                    },
                    'messages as public_unread_count' => static function (Builder $builder): void {
                        $builder->whereNull('seen_at')->where('sender_type', 'admin');
                    },
                ])
                ->findOrFail($threadId);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new ModelNotFoundException();
        }
    }

    public function threadMessages(SupportChatThread $thread, int $limit = self::THREAD_MESSAGE_LIMIT, int $afterId = 0): EloquentCollection
    {
        if (!$this->schemaReady()) {
            return new EloquentCollection();
        }

        try {
            $query = $thread->messages()
                ->with([
                    'senderUser:id,username,email,img',
                    'senderAdmin:id,username,email,img',
                ]);

            if ($afterId > 0) {
                return $query
                    ->where('id', '>', $afterId)
                    ->orderBy('id')
                    ->get();
            }

            return $query
                ->orderByDesc('id')
                ->limit(max(1, min(100, $limit)))
                ->get()
                ->sortBy('id')
                ->values();
        } catch (\Throwable) {
            return new EloquentCollection();
        }
    }

    public function adminAssignees(): Collection
    {
        try {
            $query = User::query()->select(['id', 'username', 'email', 'img']);

            if ($this->coreSchema->supports('site_admins')) {
                $query->where(static function (Builder $builder): void {
                    $builder
                        ->where('id', 1)
                        ->orWhereHas('siteAdminEntry', static function (Builder $siteAdminQuery): void {
                            $siteAdminQuery->where('is_active', true);
                        });
                });
            } else {
                $query->where('id', 1);
            }

            return $query
                ->orderByRaw('id = 1 desc')
                ->orderBy('username')
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    public function activePublicThread(Request $request): ?SupportChatThread
    {
        if (!$this->schemaReady()) {
            return null;
        }

        try {
            $query = SupportChatThread::query()
                ->with([
                    'member:id,username,email,img',
                    'assignedAdmin:id,username,email,img',
                ])
                ->withCount([
                    'messages as public_unread_count' => static function (Builder $builder): void {
                        $builder->whereNull('seen_at')->where('sender_type', 'admin');
                    },
                ])
                ->orderByDesc('last_message_at')
                ->orderByDesc('id');

            if ($request->user()) {
                return $query->where('user_id', (int) $request->user()->getKey())->first();
            }

            $token = $this->publicCookieToken($request);
            if ($token === '') {
                return null;
            }

            return $query->where('visitor_token', $token)->first();
        } catch (\Throwable) {
            return null;
        }
    }

    public function startPublicThread(Request $request): SupportChatThread
    {
        $this->guardPublicLocalAvailability($request);

        $payload = $this->validateStartPayload($request);
        $this->ensurePublicThrottleIsAvailable($request);

        $thread = $this->resolveOrCreateThread(
            $request,
            (string) ($payload['guest_name'] ?? ''),
            (string) ($payload['guest_email'] ?? '')
        );

        $body = $this->validateMessageBody((string) $payload['message']);
        $appended = $this->appendMessage($thread, $body, $request->user());
        $this->hitPublicThrottle($request);

        if (!$appended['is_duplicate']) {
            $this->handleAiAutoReplyIfEnabled($thread);
        }

        return $this->findThreadOrFail((int) $thread->getKey());
    }

    public function sendPublicMessage(Request $request): SupportChatThread
    {
        $this->guardPublicLocalAvailability($request);

        $thread = $this->activePublicThread($request);
        if (!$thread || !$this->canAccessPublicThread($request, $thread)) {
            throw ValidationException::withMessages([
                'message' => __('support_chat::messages.thread_not_found'),
            ]);
        }

        $payload = $this->validateMessagePayload($request);
        $this->ensurePublicThrottleIsAvailable($request);

        $body = $this->validateMessageBody((string) $payload['message']);
        $appended = $this->appendMessage($thread, $body, $request->user());
        $this->hitPublicThrottle($request);

        if (!$appended['is_duplicate']) {
            $this->handleAiAutoReplyIfEnabled($thread);
        }

        return $this->findThreadOrFail((int) $thread->getKey());
    }

    public function handleAiAutoReplyIfEnabled(SupportChatThread $thread): ?SupportChatMessage
    {
        $settings = $this->settings();
        $aiEnabled = !empty($settings['ai_enabled']);
        $aiMode = (string) ($settings['ai_mode'] ?? 'auto_reply');

        if (!$aiEnabled || !in_array($aiMode, ['auto_reply', 'always_ai'], true)) {
            return null;
        }

        // Deduplication 1: if the last message in this thread was already an AI message, do not duplicate!
        $lastMessage = $thread->messages()->latest('id')->first();
        if ($lastMessage && in_array($lastMessage->sender_type, ['ai', 'bot'], true)) {
            return $lastMessage;
        }

        // Deduplication 2: if an AI message was created in this thread in the last 4 seconds, skip
        $recentAi = $thread->messages()
            ->whereIn('sender_type', ['ai', 'bot'])
            ->where('created_at', '>=', now()->subSeconds(4))
            ->exists();
        if ($recentAi) {
            return null;
        }

        try {
            $reply = $this->aiService->generateReply($thread);
            if (!empty($reply)) {
                $message = $thread->messages()->create([
                    'sender_type' => 'ai',
                    'sender_user_id' => null,
                    'sender_admin_id' => null,
                    'body' => $reply,
                    'created_at' => now(),
                ]);

                $thread->forceFill([
                    'last_sender_type' => 'ai',
                    'last_message_at' => now(),
                ])->save();

                return $message;
            }
        } catch (\Throwable $e) {
            logger()->error('[SupportChatService] AI auto-reply error: ' . $e->getMessage());
        }

        return null;
    }

    public function suggestAiAdminReply(SupportChatThread $thread, ?string $instruction = null): string
    {
        $reply = $this->aiService->generateReply($thread, $instruction, true);

        return !empty($reply) ? $reply : (string) __('support_chat::messages.ai_no_suggestion');
    }

    public function adminReply(SupportChatThread $thread, User $admin, string $body): SupportChatMessage
    {
        $this->ensureSchemaReady();

        $body = $this->validateMessageBody($body);
        if ($cooldownMessage = $this->securityThrottle->actionMessage($admin, 'private_message')) {
            throw ValidationException::withMessages([
                'message' => $cooldownMessage,
            ]);
        }

        try {
            $message = $thread->messages()->create([
                'sender_type' => 'admin',
                'sender_user_id' => null,
                'sender_admin_id' => (int) $admin->getKey(),
                'body' => $body,
                'created_at' => now(),
            ]);

            $thread->forceFill([
                'status' => 'pending',
                'last_sender_type' => 'admin',
                'last_message_at' => now(),
                'assigned_admin_id' => $thread->assigned_admin_id ?: (int) $admin->getKey(),
            ])->save();

            $this->securityThrottle->hitAction($admin, 'private_message');

            return $message->load(['senderAdmin:id,username,email,img']);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'message' => __('messages.error_prefix'),
            ]);
        }
    }

    public function adminPoll(SupportChatThread $thread, int $afterId): EloquentCollection
    {
        $messages = $this->threadMessages($thread, self::THREAD_MESSAGE_LIMIT, $afterId);
        $this->markAdminSeen($thread);

        return $messages;
    }

    public function publicPoll(SupportChatThread $thread, int $afterId): EloquentCollection
    {
        $messages = $this->threadMessages($thread, self::THREAD_MESSAGE_LIMIT, $afterId);
        $this->markPublicSeen($thread);

        return $messages;
    }

    public function markAdminSeen(SupportChatThread $thread): void
    {
        if (!$this->schemaReady()) {
            return;
        }

        try {
            $thread->messages()
                ->whereNull('seen_at')
                ->whereIn('sender_type', ['guest', 'member'])
                ->update(['seen_at' => now()]);
        } catch (\Throwable) {
        }
    }

    public function markPublicSeen(SupportChatThread $thread): void
    {
        if (!$this->schemaReady()) {
            return;
        }

        try {
            $thread->messages()
                ->whereNull('seen_at')
                ->where('sender_type', 'admin')
                ->update(['seen_at' => now()]);
        } catch (\Throwable) {
        }
    }

    public function assignThread(SupportChatThread $thread, ?int $adminId): void
    {
        $this->ensureSchemaReady();

        if ($adminId === null || $adminId <= 0) {
            $thread->forceFill(['assigned_admin_id' => null])->save();

            return;
        }

        $valid = $this->adminAssignees()->contains(static fn (User $admin): bool => (int) $admin->getKey() === $adminId);
        if (!$valid) {
            throw ValidationException::withMessages([
                'assigned_admin_id' => __('support_chat::messages.invalid_assignee'),
            ]);
        }

        $thread->forceFill(['assigned_admin_id' => $adminId])->save();
    }

    public function updateStatus(SupportChatThread $thread, string $status): void
    {
        $this->ensureSchemaReady();

        if (!in_array($status, ['open', 'pending', 'closed'], true)) {
            throw ValidationException::withMessages([
                'status' => __('support_chat::messages.invalid_status'),
            ]);
        }

        $thread->forceFill(['status' => $status])->save();
    }

    public function canAccessPublicThread(Request $request, SupportChatThread $thread): bool
    {
        if ($request->user()) {
            return (int) $thread->user_id === (int) $request->user()->getKey();
        }

        return $thread->visitor_token !== null
            && hash_equals((string) $thread->visitor_token, $this->publicCookieToken($request));
    }

    public function publicCookieToken(Request $request): string
    {
        $token = trim((string) $request->cookie(self::COOKIE_NAME, ''));

        return preg_match('/^[A-Za-z0-9]{24,80}$/', $token) === 1 ? $token : '';
    }

    public function guestCookie(string $token, Request $request): Cookie
    {
        return Cookie::create(
            self::COOKIE_NAME,
            $token,
            now()->addDays(30),
            '/',
            null,
            $request->isSecure(),
            false,
            false,
            'Lax'
        );
    }

    public function externalUrl(array $settings): ?string
    {
        $mode = $settings['channel_mode'] ?? 'local';

        if ($mode === 'whatsapp') {
            $number = preg_replace('/\D+/', '', (string) ($settings['whatsapp_number'] ?? '')) ?? '';
            if ($number === '') {
                return null;
            }

            $prefill = trim((string) ($settings['whatsapp_prefill'] ?? ''));
            $url = 'https://wa.me/' . $number;

            return $prefill !== '' ? $url . '?text=' . rawurlencode($prefill) : $url;
        }

        if ($mode === 'messenger') {
            $url = trim((string) ($settings['messenger_url'] ?? ''));

            return $url !== '' ? $url : null;
        }

        return null;
    }

    public function externalLabel(array $settings): string
    {
        return match ($settings['channel_mode'] ?? 'local') {
            'whatsapp' => __('support_chat::messages.whatsapp_channel'),
            'messenger' => __('support_chat::messages.messenger_channel'),
            default => __('support_chat::messages.local_channel'),
        };
    }

    public function isExcludedRequest(Request $request): bool
    {
        $path = trim((string) $request->path(), '/');
        $routeName = (string) optional($request->route())->getName();

        $blockedPatterns = [
            'admin*',
            'login*',
            'register*',
            'password*',
            'logout*',
            'auth*',
            'install*',
            'installer*',
            'api*',
            'bn.php',
            'link.php',
            'smart.php',
            'show.php',
            'embed*',
            'captcha*',
        ];

        foreach ($blockedPatterns as $pattern) {
            if (Str::is($pattern, $path) || Str::is($pattern, $routeName)) {
                return true;
            }
        }

        foreach (SupportChatSettings::visibilityRules() as $pattern) {
            if (Str::is($pattern, $path) || Str::is($pattern, '/' . $path) || Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    private function resolveOrCreateThread(Request $request, string $guestName = '', string $guestEmail = ''): SupportChatThread
    {
        $user = $request->user();
        $thread = $this->activePublicThread($request);

        if ($thread) {
            if (!$user && trim($guestName) !== '' && trim((string) $thread->visitor_name) === '') {
                $thread->visitor_name = trim($guestName);
            }
            if (!$user && trim($guestEmail) !== '' && trim((string) $thread->visitor_email) === '') {
                $thread->visitor_email = trim($guestEmail);
            }
            if ($thread->status === 'closed') {
                $thread->status = 'open';
            }
            $thread->save();

            return $thread;
        }

        $token = $user ? null : Str::random(40);

        try {
            return SupportChatThread::query()->create([
                'user_id' => $user ? (int) $user->getKey() : null,
                'visitor_name' => $user ? null : trim($guestName),
                'visitor_email' => $user ? null : trim($guestEmail),
                'visitor_token' => $token,
                'status' => 'open',
                'assigned_admin_id' => null,
                'last_sender_type' => $user ? 'member' : 'guest',
                'last_message_at' => now(),
                'started_page_url' => $this->normalizePageUrl((string) $request->input('page_url', '')),
                'started_page_title' => Str::limit(trim((string) $request->input('page_title', '')), 255, ''),
                'session_meta' => [
                    'ip' => $request->ip(),
                    'locale' => app()->getLocale(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                    'referrer' => Str::limit((string) $request->headers->get('referer', ''), 500, ''),
                ],
            ]);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'message' => __('messages.error_prefix'),
            ]);
        }
    }

    /**
     * @return array{message: SupportChatMessage, is_duplicate: bool}
     */
    private function appendMessage(SupportChatThread $thread, string $body, ?User $user = null): array
    {
        $senderType = $user ? 'member' : 'guest';
        $senderUserId = $user ? (int) $user->getKey() : null;

        try {
            // Deduplication: skip if identical message created in last 4 seconds
            $duplicateQuery = $thread->messages()
                ->where('body', $body)
                ->where('sender_type', $senderType)
                ->where('created_at', '>=', now()->subSeconds(4));

            if ($senderUserId === null) {
                $duplicateQuery->whereNull('sender_user_id');
            } else {
                $duplicateQuery->where('sender_user_id', $senderUserId);
            }

            if ($duplicate = $duplicateQuery->first()) {
                return [
                    'message' => $duplicate,
                    'is_duplicate' => true,
                ];
            }

            $message = $thread->messages()->create([
                'sender_type' => $senderType,
                'sender_user_id' => $senderUserId,
                'sender_admin_id' => null,
                'body' => $body,
                'created_at' => now(),
            ]);

            $thread->forceFill([
                'status' => 'open',
                'last_sender_type' => $senderType,
                'last_message_at' => now(),
            ])->save();

            return [
                'message' => $message,
                'is_duplicate' => false,
            ];
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'message' => __('messages.error_prefix'),
            ]);
        }
    }

    private function validateStartPayload(Request $request): array
    {
        $rules = [
            'message' => ['required', 'string', 'max:2000'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'page_title' => ['nullable', 'string', 'max:255'],
        ];

        if (!$request->user()) {
            $rules['guest_name'] = ['required', 'string', 'max:120'];
            $rules['guest_email'] = ['required', 'email:rfc', 'max:190'];
        }

        return Validator::make($request->all(), $rules, [
            'guest_name.required' => __('support_chat::messages.guest_identity_required'),
            'guest_email.required' => __('support_chat::messages.guest_identity_required'),
            'message.required' => __('support_chat::messages.message_required'),
            'message.max' => __('support_chat::messages.message_too_long'),
        ])->validate();
    }

    private function validateMessagePayload(Request $request): array
    {
        return Validator::make($request->all(), [
            'message' => ['required', 'string', 'max:2000'],
        ], [
            'message.required' => __('support_chat::messages.message_required'),
            'message.max' => __('support_chat::messages.message_too_long'),
        ])->validate();
    }

    private function validateMessageBody(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages([
                'message' => __('support_chat::messages.message_required'),
            ]);
        }

        if (mb_strlen($body) > 2000) {
            throw ValidationException::withMessages([
                'message' => __('support_chat::messages.message_too_long'),
            ]);
        }

        if ($violation = $this->securityPolicy->textViolation($body, 'messages')) {
            throw ValidationException::withMessages([
                'message' => $violation,
            ]);
        }

        return $body;
    }

    private function ensurePublicThrottleIsAvailable(Request $request): void
    {
        $user = $request->user();

        if ($user) {
            if ($cooldownMessage = $this->securityThrottle->actionMessage($user, 'private_message')) {
                throw ValidationException::withMessages([
                    'message' => $cooldownMessage,
                ]);
            }

            return;
        }

        $key = sprintf(
            'support-chat:guest:%s:%s',
            sha1((string) $request->ip()),
            sha1($this->publicCookieToken($request) ?: (string) $request->userAgent())
        );

        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw ValidationException::withMessages([
                'message' => __('support_chat::messages.cooldown_message', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }
    }

    private function hitPublicThrottle(Request $request): void
    {
        if ($request->user()) {
            $this->securityThrottle->hitAction($request->user(), 'private_message');

            return;
        }

        $key = sprintf(
            'support-chat:guest:%s:%s',
            sha1((string) $request->ip()),
            sha1($this->publicCookieToken($request) ?: (string) $request->userAgent())
        );
        RateLimiter::hit($key, self::GUEST_COOLDOWN_SECONDS);
    }

    private function guardPublicLocalAvailability(Request $request): void
    {
        $settings = $this->settings();

        if (($settings['channel_mode'] ?? 'local') !== 'local' || (int) ($settings['widget_enabled'] ?? 0) !== 1 || !$this->schemaReady()) {
            throw ValidationException::withMessages([
                'message' => __('support_chat::messages.local_chat_unavailable'),
            ]);
        }

        if ($this->isExcludedRequest($request)) {
            throw ValidationException::withMessages([
                'message' => __('support_chat::messages.local_chat_unavailable'),
            ]);
        }
    }

    private function ensureSchemaReady(): void
    {
        if (!$this->schemaReady()) {
            throw ValidationException::withMessages([
                'message' => __('support_chat::messages.local_chat_unavailable'),
            ]);
        }
    }

    private function normalizePageUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (mb_strlen($url) > 2048) {
            $url = mb_substr($url, 0, 2048);
        }

        return $url;
    }
}
