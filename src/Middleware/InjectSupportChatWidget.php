<?php

declare(strict_types=1);

namespace MyAds\Plugins\SupportChat\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use MyAds\Plugins\SupportChat\Services\SupportChatService;
use Symfony\Component\HttpFoundation\Response;

class InjectSupportChatWidget
{
    public function __construct(
        private readonly SupportChatService $service
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$response instanceof IlluminateResponse) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }

        $content = (string) $response->getContent();
        if ($content === '' || str_contains($content, 'data-support-chat-widget')) {
            return $response;
        }

        try {
            $payload = $this->service->widgetPayload($request);
            if (!$payload['should_render']) {
                return $response;
            }

            $headMarkup = view('support_chat::partials.widget_head', $payload)->render();
            $bodyMarkup = view('support_chat::partials.widget', $payload)->render();

            if (str_contains($content, '</head>')) {
                $content = str_replace('</head>', $headMarkup . PHP_EOL . '</head>', $content);
            }

            if (str_contains($content, '</body>')) {
                $content = str_replace('</body>', $bodyMarkup . PHP_EOL . '</body>', $content);
            }

            $response->setContent($content);
        } catch (\Throwable) {
            // Keep the public site resilient even if widget injection fails.
        }

        return $response;
    }
}
