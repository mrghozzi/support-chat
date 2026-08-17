<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use MyAds\Plugins\SupportChat\Controllers\AdminSupportChatController;
use MyAds\Plugins\SupportChat\Controllers\PublicSupportChatController;

Route::middleware(['web'])->group(function (): void {
    Route::get('/support-chat/assets/{path}', [PublicSupportChatController::class, 'asset'])
        ->where('path', '.*')
        ->name('support_chat.asset');

    Route::get('/support-chat/thread', [PublicSupportChatController::class, 'thread'])
        ->middleware('throttle:120,1')
        ->name('support_chat.thread.current');
    Route::get('/support-chat/poll', [PublicSupportChatController::class, 'poll'])
        ->middleware('throttle:120,1')
        ->name('support_chat.thread.poll');
    Route::post('/support-chat/start', [PublicSupportChatController::class, 'start'])
        ->middleware('throttle:40,1')
        ->name('support_chat.thread.start');
    Route::post('/support-chat/message', [PublicSupportChatController::class, 'message'])
        ->middleware('throttle:40,1')
        ->name('support_chat.thread.message');
});

Route::middleware(['web', 'auth', 'admin'])->group(function (): void {
    Route::get('/admin/support-chat', [AdminSupportChatController::class, 'index'])
        ->name('admin.support_chat.index');
    Route::post('/admin/support-chat/settings', [AdminSupportChatController::class, 'updateSettings'])
        ->name('admin.support_chat.settings.update');
    Route::post('/admin/support-chat/test-ai', [AdminSupportChatController::class, 'testAi'])
        ->name('admin.support_chat.ai.test');
    Route::get('/admin/support-chat/threads/{threadId}/poll', [AdminSupportChatController::class, 'poll'])
        ->name('admin.support_chat.threads.poll');
    Route::match(['GET', 'POST'], '/admin/support-chat/threads/{threadId}/reply', [AdminSupportChatController::class, 'reply'])
        ->name('admin.support_chat.threads.reply');
    Route::post('/admin/support-chat/threads/{threadId}/ai-suggest', [AdminSupportChatController::class, 'aiSuggest'])
        ->name('admin.support_chat.threads.ai_suggest');
    Route::post('/admin/support-chat/threads/{threadId}/assign', [AdminSupportChatController::class, 'assign'])
        ->name('admin.support_chat.threads.assign');
    Route::post('/admin/support-chat/threads/{threadId}/status', [AdminSupportChatController::class, 'updateStatus'])
        ->name('admin.support_chat.threads.status');
});
