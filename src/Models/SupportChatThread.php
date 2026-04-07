<?php

declare(strict_types=1);

namespace MyAds\Plugins\SupportChat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class SupportChatThread extends Model
{
    protected $table = 'support_chat_threads';

    protected $fillable = [
        'user_id',
        'visitor_name',
        'visitor_email',
        'visitor_token',
        'status',
        'assigned_admin_id',
        'last_sender_type',
        'last_message_at',
        'started_page_url',
        'started_page_title',
        'session_meta',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'session_meta' => 'array',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportChatMessage::class, 'thread_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportChatMessage::class, 'thread_id')->latestOfMany();
    }

    public function scopeAwaitingReply(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', 'closed')
            ->whereIn('last_sender_type', ['guest', 'member']);
    }

    public function participantName(): string
    {
        if ($this->relationLoaded('member') && $this->member) {
            return (string) $this->member->username;
        }

        if ((int) $this->user_id > 0) {
            return (string) optional($this->member)->username ?: __('support_chat::messages.member_fallback_name');
        }

        return trim((string) $this->visitor_name) !== ''
            ? (string) $this->visitor_name
            : __('support_chat::messages.guest_fallback_name');
    }

    public function participantEmail(): ?string
    {
        if ($this->relationLoaded('member') && $this->member) {
            return (string) $this->member->email;
        }

        if ((int) $this->user_id > 0) {
            return optional($this->member)->email;
        }

        $email = trim((string) $this->visitor_email);

        return $email !== '' ? $email : null;
    }

    public function participantAvatarUrl(): string
    {
        if ($this->relationLoaded('member') && $this->member) {
            return $this->member->avatarUrl();
        }

        if ((int) $this->user_id > 0 && $this->member) {
            return $this->member->avatarUrl();
        }

        return asset('upload/avatar.png');
    }

    public function isGuestThread(): bool
    {
        return (int) $this->user_id <= 0;
    }

    public function lastPreview(): string
    {
        $body = trim((string) optional($this->latestMessage)->body);

        if ($body === '') {
            return __('support_chat::messages.no_messages_yet');
        }

        return Str::limit($body, 96);
    }
}
