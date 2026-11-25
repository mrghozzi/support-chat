<?php

declare(strict_types=1);

namespace MyAds\Plugins\SupportChat\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use MyAds\Plugins\SupportChat\Services\SupportChatService;

class PublicSupportChatController extends Controller
{
    public function __construct(
        private readonly SupportChatService $service
    ) {
    }

    public function asset(string $path)
    {
        if (str_contains($path, "\0") || str_contains($path, '..')) {
            abort(404);
        }

        $assetBase = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets');
        abort_if($assetBase === false, 404);

        $assetPath = realpath($assetBase . DIRECTORY_SEPARATOR . ltrim($path, '/\\'));
        $normalizedBase = rtrim(str_replace('\\', '/', mb_strtolower((string) $assetBase)), '/') . '/';
        $normalizedAsset = str_replace('\\', '/', mb_strtolower((string) $assetPath));

        abort_if(
            $assetPath === false || !str_starts_with($normalizedAsset, $normalizedBase) || !File::isFile($assetPath),
            404
        );

        $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            default => null,
        };

        abort_if($mimeType === null, 404);

        return response()->file($assetPath, [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function thread(Request $request): JsonResponse
    {
        try {
            $thread = $this->service->activePublicThread($request);

            if (!$thread) {
                return response()->json([
                    'success' => true,
                    'thread' => null,
                    'html' => '',
                    'latest_id' => 0,
                    'count' => 0,
                ]);
            }

            if (!$this->service->canAccessPublicThread($request, $thread)) {
                return response()->json([
                    'success' => false,
                    'message' => __('support_chat::messages.thread_not_found'),
                ], 404);
            }

            $this->service->markPublicSeen($thread);
            $messages = $this->service->threadMessages($thread);
            $thread = $this->service->findThreadOrFail((int) $thread->getKey());
            $response = response()->json([
                'success' => true,
                'thread' => [
                    'id' => (int) $thread->getKey(),
                    'status' => (string) $thread->status,
                    'participant_name' => $thread->participantName(),
                    'public_unread_count' => (int) ($thread->public_unread_count ?? 0),
                ],
                'html' => view('support_chat::partials.widget_messages', [
                    'messages' => $messages,
                    'thread' => $thread,
                    'currentPublicUserId' => (int) ($request->user()?->getKey() ?? 0),
                    'itemsOnly' => false,
                ])->render(),
                'latest_id' => (int) ($messages->last()->id ?? 0),
                'count' => $messages->count(),
            ]);

            if (!$request->user() && $thread->visitor_token) {
                $response->withCookie($this->service->guestCookie((string) $thread->visitor_token, $request));
            }

            return $response;
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => __('support_chat::messages.local_chat_unavailable'),
            ], 409);
        }
    }

    public function poll(Request $request): JsonResponse
    {
        try {
            $thread = $this->service->activePublicThread($request);
            if (!$thread || !$this->service->canAccessPublicThread($request, $thread)) {
                return response()->json([
                    'success' => true,
                    'html' => '',
                    'latest_id' => max(0, (int) $request->query('after_id', 0)),
                    'count' => 0,
                ]);
            }

            $messages = $this->service->publicPoll($thread, max(0, (int) $request->query('after_id', 0)));
            $response = response()->json([
                'success' => true,
                'html' => view('support_chat::partials.widget_messages', [
                    'messages' => $messages,
                    'thread' => $thread,
                    'currentPublicUserId' => (int) ($request->user()?->getKey() ?? 0),
                    'itemsOnly' => true,
                ])->render(),
                'latest_id' => (int) ($messages->last()->id ?? $request->integer('after_id', 0)),
                'count' => $messages->count(),
            ]);

            if (!$request->user() && $thread->visitor_token) {
                $response->withCookie($this->service->guestCookie((string) $thread->visitor_token, $request));
            }

            return $response;
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => __('support_chat::messages.local_chat_unavailable'),
            ], 409);
        }
    }

    public function start(Request $request): JsonResponse
    {
        try {
            $thread = $this->service->startPublicThread($request);
            $messages = $this->service->threadMessages($thread);
            $response = response()->json([
                'success' => true,
                'thread' => [
                    'id' => (int) $thread->getKey(),
                    'status' => (string) $thread->status,
                    'participant_name' => $thread->participantName(),
                ],
                'html' => view('support_chat::partials.widget_messages', [
                    'messages' => $messages,
                    'thread' => $thread,
                    'currentPublicUserId' => (int) ($request->user()?->getKey() ?? 0),
                    'itemsOnly' => false,
                ])->render(),
                'latest_id' => (int) ($messages->last()->id ?? 0),
            ]);

            if (!$request->user() && $thread->visitor_token) {
                $response->withCookie($this->service->guestCookie((string) $thread->visitor_token, $request));
            }

            return $response;
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->errors()['message'][0]
                    ?? $exception->errors()['guest_name'][0]
                    ?? $exception->errors()['guest_email'][0]
                    ?? __('messages.error_prefix'),
                'errors' => $exception->errors(),
            ], 422);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => __('messages.error_prefix'),
            ], 500);
        }
    }

    public function message(Request $request): JsonResponse
    {
        try {
            $latestBefore = (int) $request->input('latest_id', 0);
            $thread = $this->service->sendPublicMessage($request);
            $messages = $this->service->threadMessages($thread, 10);
            $newMessages = $latestBefore > 0
                ? $messages->where('id', '>', $latestBefore)->values()
                : $messages->slice(-2)->values();

            if ($newMessages->isEmpty()) {
                $newMessages = $messages->slice(-1)->values();
            }

            $response = response()->json([
                'success' => true,
                'thread' => [
                    'id' => (int) $thread->getKey(),
                    'status' => (string) $thread->status,
                    'participant_name' => $thread->participantName(),
                ],
                'html' => view('support_chat::partials.widget_messages', [
                    'messages' => $newMessages,
                    'thread' => $thread,
                    'currentPublicUserId' => (int) ($request->user()?->getKey() ?? 0),
                    'itemsOnly' => true,
                ])->render(),
                'latest_id' => (int) ($messages->last()->id ?? 0),
            ]);

            if (!$request->user() && $thread->visitor_token) {
                $response->withCookie($this->service->guestCookie((string) $thread->visitor_token, $request));
            }

            return $response;
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->errors()['message'][0] ?? __('messages.error_prefix'),
                'errors' => $exception->errors(),
            ], 422);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => __('messages.error_prefix'),
            ], 500);
        }
    }
}
