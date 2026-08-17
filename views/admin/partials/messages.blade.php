@php
    $messages = $messages ?? collect();
@endphp

@unless($itemsOnly ?? false)
    <div class="support-chat-transcript__inner">
@endunless

@forelse($messages as $message)
    @php
        $isAdminMessage = $message->sender_type === 'admin';
        $isAiMessage = in_array($message->sender_type, ['ai', 'bot'], true);
        $bubbleClass = $isAdminMessage ? 'is-admin' : ($isAiMessage ? 'is-ai' : 'is-visitor');
    @endphp
    <article class="support-chat-bubble {{ $bubbleClass }}" data-message-id="{{ $message->id }}">
        <div class="support-chat-bubble__avatar">
            @if($isAiMessage)
                <div class="support-chat-avatar-hex-wrap is-ai-avatar">
                    <div class="support-chat-avatar-hex-content bg-soft-info text-info">
                        <i class="feather-cpu fs-5"></i>
                    </div>
                </div>
            @elseif($isAdminMessage)
                <div class="support-chat-avatar-hex-wrap is-admin-avatar">
                    <img src="{{ $message->avatarUrl() }}" alt="{{ $message->displayName() }}" class="support-chat-avatar-hex-img">
                </div>
            @else
                <div class="support-chat-avatar-hex-wrap is-user-avatar">
                    <img src="{{ $message->avatarUrl() }}" alt="{{ $message->displayName() }}" class="support-chat-avatar-hex-img">
                </div>
            @endif
        </div>
        <div class="support-chat-bubble__body">
            <div class="support-chat-bubble__meta">
                <div class="d-flex align-items-center gap-1">
                    <strong>{{ $message->displayName() }}</strong>
                    @if($isAiMessage)
                        <span class="badge bg-soft-info text-info fs-11 py-1 px-2 rounded-pill"><i class="feather-zap me-1"></i>{{ __('support_chat::messages.ai_badge') }}</span>
                    @elseif($isAdminMessage)
                        <span class="badge bg-soft-primary text-primary fs-11 py-1 px-2 rounded-pill"><i class="feather-shield me-1"></i>{{ __('support_chat::messages.admin_badge') }}</span>
                    @else
                        <span class="badge bg-soft-secondary text-secondary fs-11 py-1 px-2 rounded-pill">{{ __('support_chat::messages.member_badge') }}</span>
                    @endif
                </div>
                <span class="text-muted small fs-11">{{ optional($message->created_at)->format('H:i') }}</span>
            </div>
            <div class="support-chat-bubble__text">
                {!! nl2br(e($message->body)) !!}
            </div>
        </div>
    </article>
@empty
    @unless($itemsOnly ?? false)
        <div class="support-chat-empty text-center py-5">
            <div class="bg-soft-primary text-primary rounded-circle d-inline-flex p-3 mb-3">
                <i class="feather-message-circle fs-2"></i>
            </div>
            <h5 class="fw-bold mb-1">{{ __('support_chat::messages.empty_thread_title') }}</h5>
            <p class="text-muted small mb-0">{{ __('support_chat::messages.empty_thread_body') }}</p>
        </div>
    @endunless
@endforelse

@unless($itemsOnly ?? false)
    </div>
@endunless
