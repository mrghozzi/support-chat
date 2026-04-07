<?php

declare(strict_types=1);

namespace MyAds\Plugins\SupportChat\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use MyAds\Plugins\SupportChat\Services\SupportChatSchema;
use MyAds\Plugins\SupportChat\Services\SupportChatService;
use MyAds\Plugins\SupportChat\SupportChatSettings;

class AdminSupportChatController extends Controller
{
    public function __construct(
        private readonly SupportChatService $service,
        private readonly SupportChatSchema $schema
    ) {
    }

    public function index(Request $request)
    {
        $settings = $this->service->settings();
        $schemaReady = $this->schema->supportsStorage();
        $setupNotice = $this->schema->notice();
        $filter = $request->query('filter', 'open');
        $search = trim((string) $request->query('q', ''));
        $threads = $settings['channel_mode'] === 'local' && $schemaReady
            ? $this->service->adminThreads($filter, $search)
            : new LengthAwarePaginator([], 0, 16, max(1, $request->integer('page', 1)), [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);

        $activeThread = null;
        $messages = collect();

        if ($settings['channel_mode'] === 'local' && $schemaReady && $threads->count() > 0) {
            $requestedThreadId = (int) $request->query('thread', 0);

            try {
                $activeThread = $requestedThreadId > 0
                    ? $this->service->findThreadOrFail($requestedThreadId)
                    : $this->service->findThreadOrFail((int) $threads->first()->id);
            } catch (\Throwable) {
                $activeThread = null;
            }

            if ($activeThread) {
                $this->service->markAdminSeen($activeThread);
                $messages = $this->service->threadMessages($activeThread);
                $activeThread = $this->service->findThreadOrFail((int) $activeThread->getKey());
            }
        }

        return view('support_chat::admin.index', [
            'settings' => $settings,
            'schemaReady' => $schemaReady,
            'setupNotice' => $setupNotice,
            'threads' => $threads,
            'activeThread' => $activeThread,
            'messages' => $messages,
            'filter' => in_array($filter, ['open', 'awaiting_reply', 'closed'], true) ? $filter : 'open',
            'search' => $search,
            'assignees' => $this->service->adminAssignees(),
            'currentAdminId' => (int) $request->user()->getKey(),
            'externalUrl' => $this->service->externalUrl($settings),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'channel_mode' => ['required', 'in:local,whatsapp,messenger'],
            'widget_enabled' => ['nullable', 'boolean'],
            'team_name' => ['required', 'string', 'max:120'],
            'welcome_message' => ['required', 'string', 'max:400'],
            'whatsapp_number' => ['nullable', 'string', 'max:40'],
            'whatsapp_prefill' => ['nullable', 'string', 'max:400'],
            'messenger_url' => ['nullable', 'url', 'max:255'],
            'visibility_rules' => ['nullable', 'string', 'max:4000'],
        ]);

        SupportChatSettings::save($validated + [
            'widget_enabled' => $request->boolean('widget_enabled'),
        ]);

        return redirect()->route('admin.support_chat.index')
            ->with('success', __('support_chat::messages.settings_saved'));
    }

    public function reply(Request $request, int $threadId): JsonResponse
    {
        try {
            $thread = $this->service->findThreadOrFail($threadId);
            $message = $this->service->adminReply($thread, $request->user(), (string) $request->input('message', ''));

            return response()->json([
                'success' => true,
                'html' => view('support_chat::admin.partials.messages', [
                    'messages' => collect([$message]),
                    'activeThread' => $thread,
                    'currentAdminId' => (int) $request->user()->getKey(),
                    'itemsOnly' => true,
                ])->render(),
                'latest_id' => (int) $message->getKey(),
                'status_label' => __('support_chat::messages.status_pending'),
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->errors()['message'][0] ?? $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => __('messages.error_prefix'),
            ], 500);
        }
    }

    public function poll(Request $request, int $threadId): JsonResponse
    {
        try {
            $thread = $this->service->findThreadOrFail($threadId);
            $messages = $this->service->adminPoll($thread, max(0, (int) $request->query('after_id', 0)));

            return response()->json([
                'success' => true,
                'html' => view('support_chat::admin.partials.messages', [
                    'messages' => $messages,
                    'activeThread' => $thread,
                    'currentAdminId' => (int) $request->user()->getKey(),
                    'itemsOnly' => true,
                ])->render(),
                'latest_id' => (int) ($messages->last()->id ?? $request->integer('after_id', 0)),
                'count' => $messages->count(),
            ]);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => __('support_chat::messages.thread_not_found'),
            ], 404);
        }
    }

    public function assign(Request $request, int $threadId): JsonResponse|RedirectResponse
    {
        try {
            $thread = $this->service->findThreadOrFail($threadId);
            $value = $request->input('assigned_admin_id');
            $this->service->assignThread($thread, $value !== null && $value !== '' ? (int) $value : null);
            $refreshed = $this->service->findThreadOrFail($threadId);
        } catch (ValidationException $exception) {
            return $this->assignmentErrorResponse($request, $exception);
        } catch (\Throwable) {
            return $this->assignmentErrorResponse($request);
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('support_chat::messages.assignment_saved'),
                'assigned_label' => optional($refreshed->assignedAdmin)->username ?: __('support_chat::messages.unassigned_label'),
            ]);
        }

        return redirect()->route('admin.support_chat.index', ['thread' => $threadId])
            ->with('success', __('support_chat::messages.assignment_saved'));
    }

    public function updateStatus(Request $request, int $threadId): JsonResponse|RedirectResponse
    {
        try {
            $thread = $this->service->findThreadOrFail($threadId);
            $this->service->updateStatus($thread, (string) $request->input('status', ''));
        } catch (ValidationException $exception) {
            return $this->statusErrorResponse($request, $exception);
        } catch (\Throwable) {
            return $this->statusErrorResponse($request);
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('support_chat::messages.status_saved'),
            ]);
        }

        return redirect()->route('admin.support_chat.index', ['thread' => $threadId])
            ->with('success', __('support_chat::messages.status_saved'));
    }

    private function assignmentErrorResponse(Request $request, ?ValidationException $exception = null): JsonResponse|RedirectResponse
    {
        $message = $exception?->errors()['assigned_admin_id'][0]
            ?? $exception?->errors()['message'][0]
            ?? __('support_chat::messages.invalid_assignee');

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        return redirect()->route('admin.support_chat.index')->with('error', $message);
    }

    private function statusErrorResponse(Request $request, ?ValidationException $exception = null): JsonResponse|RedirectResponse
    {
        $message = $exception?->errors()['status'][0]
            ?? $exception?->errors()['message'][0]
            ?? __('support_chat::messages.invalid_status');

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        return redirect()->route('admin.support_chat.index')->with('error', $message);
    }
}
