<?php

declare(strict_types=1);

namespace MyAds\Plugins\SupportChat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportChatMessage extends Model
{
    protected $table = 'support_chat_messages';

    public const UPDATED_AT = null;
    public $timestamps = false;

    protected $fillable = [
        'thread_id',
        'sender_type',
        'sender_user_id',
        'sender_admin_id',
        'body',
        'seen_at',
        'created_at',
    ];

    protected $casts = [
        'seen_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(SupportChatThread::class, 'thread_id');
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function senderAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_admin_id');
    }

    public function displayName(): string
    {
        return match ($this->sender_type) {
            'ai', 'bot' => (string) (\MyAds\Plugins\SupportChat\SupportChatSettings::get('ai_bot_name') ?: __('support_chat::messages.ai_bot_default_name')),
            'admin' => (string) optional($this->senderAdmin)->username ?: __('support_chat::messages.support_team_label'),
            'member' => (string) optional($this->senderUser)->username ?: __('support_chat::messages.member_fallback_name'),
            'system' => __('support_chat::messages.system_label'),
            default => __('support_chat::messages.guest_fallback_name'),
        };
    }

    public function avatarUrl(): string
    {
        return match ($this->sender_type) {
            'ai', 'bot' => asset('upload/avatar.png'),
            'admin' => optional($this->senderAdmin)->avatarUrl() ?: asset('upload/avatar.png'),
            'member' => optional($this->senderUser)->avatarUrl() ?: asset('upload/avatar.png'),
            default => asset('upload/avatar.png'),
        };
    }

    public function isPublicMessage(): bool
    {
        return in_array($this->sender_type, ['guest', 'member'], true);
    }

    public function isAiMessage(): bool
    {
        return in_array($this->sender_type, ['ai', 'bot'], true);
    }
}
