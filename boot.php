<?php

declare(strict_types=1);

use App\Helpers\Hooks;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use MyAds\Plugins\SupportChat\Middleware\InjectSupportChatWidget;
use MyAds\Plugins\SupportChat\Services\SupportChatAiService;
use MyAds\Plugins\SupportChat\Services\SupportChatSchema;
use MyAds\Plugins\SupportChat\Services\SupportChatService;

$supportChatBasePath = __DIR__;
$supportChatNamespace = 'MyAds\\Plugins\\SupportChat\\';

if (!function_exists('support_chat_autoload_registered')) {
    function support_chat_autoload_registered(string $supportChatNamespace, string $supportChatBasePath): bool
    {
        static $registered = false;

        if ($registered) {
            return true;
        }

        spl_autoload_register(static function (string $class) use (&$registered, $supportChatNamespace, $supportChatBasePath): void {
            if (!str_starts_with($class, $supportChatNamespace)) {
                return;
            }

            $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($supportChatNamespace)));
            $filePath = $supportChatBasePath . '/src/' . $relativePath . '.php';
            if (is_file($filePath)) {
                require_once $filePath;
            }
        });

        $registered = true;

        return true;
    }
}

support_chat_autoload_registered($supportChatNamespace, $supportChatBasePath);

app()->singleton(SupportChatSchema::class, static fn (): SupportChatSchema => new SupportChatSchema());
app()->singleton(SupportChatAiService::class, static fn (): SupportChatAiService => new SupportChatAiService());
app()->singleton(InjectSupportChatWidget::class, static fn ($app): InjectSupportChatWidget => new InjectSupportChatWidget(
    $app->make(SupportChatService::class)
));
app()->singleton(
    SupportChatService::class,
    static fn ($app): SupportChatService => new SupportChatService(
        $app->make(SupportChatSchema::class),
        $app->make(\App\Services\SecurityPolicyService::class),
        $app->make(\App\Services\SecurityThrottleService::class),
        $app->make(\App\Services\V420SchemaService::class),
        $app->make(SupportChatAiService::class)
    )
);

View::addNamespace('support_chat', __DIR__ . '/views');
app('translator')->addNamespace('support_chat', __DIR__ . '/lang');

Hooks::add_action('theme_master_head_end', static function (): void {
    static $headRendered = false;
    if ($headRendered) {
        return;
    }

    try {
        /** @var SupportChatService $service */
        $service = app(SupportChatService::class);
        $payload = $service->widgetPayload(request());

        if (!$payload['should_render']) {
            return;
        }

        $headRendered = true;
        echo view('support_chat::partials.widget_head', $payload)->render();
    } catch (\Throwable $exception) {
        Log::warning('Support chat head hook skipped.', [
            'plugin' => 'support-chat',
            'reason' => $exception->getMessage(),
        ]);
    }
});

Hooks::add_action('theme_master_before_body_close', static function (): void {
    static $bodyRendered = false;
    if ($bodyRendered) {
        return;
    }

    try {
        /** @var SupportChatService $service */
        $service = app(SupportChatService::class);
        $payload = $service->widgetPayload(request());

        if (!$payload['should_render']) {
            return;
        }

        $bodyRendered = true;
        echo view('support_chat::partials.widget', $payload)->render();
    } catch (\Throwable $exception) {
        Log::warning('Support chat body hook skipped.', [
            'plugin' => 'support-chat',
            'reason' => $exception->getMessage(),
        ]);
    }
});

Hooks::add_action('admin_sidebar_menu', static function (): void {
    $url = route('admin.support_chat.index');
    $isActive = request()->routeIs('admin.support_chat.*');
    $linkClass = $isActive ? 'nxl-link active' : 'nxl-link';

    $badgeHtml = '';
    try {
        if (app(\MyAds\Plugins\SupportChat\Services\SupportChatSchema::class)->supportsStorage()) {
            $unreadCount = \MyAds\Plugins\SupportChat\Models\SupportChatMessage::whereIn('sender_type', ['guest', 'member'])
                ->whereNull('seen_at')
                ->whereHas('thread', static function ($query): void {
                    $query->where('status', '!=', 'closed');
                })
                ->count();
            if ($unreadCount > 0) {
                $badgeHtml = ' <span class="badge bg-danger ms-auto">' . $unreadCount . '</span>';
            }
        }
    } catch (\Throwable) {
    }

    echo '<li class="nxl-item">'
        . '<a href="' . e($url) . '" class="' . e($linkClass) . '">'
        . '<span class="nxl-micon"><i class="feather-message-circle"></i></span>'
        . '<span class="nxl-mtext">' . e(__('support_chat::messages.support_chat_title')) . '</span>'
        . $badgeHtml
        . '</a>'
        . '</li>';
});
