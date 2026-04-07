@php
    $messages = $messages ?? collect();
@endphp

@unless($itemsOnly ?? false)
    <div class="support-chat-transcript__inner">
@endunless

@forelse($messages as $message)
    @php
        $isAdminMessage = $message->sender_type === 'admin';
    @endphp
    <article class="support-chat-bubble {{ $isAdminMessage ? 'is-admin' : 'is-visitor' }}" data-message-id="{{ $message->id }}">
        <div class="support-chat-bubble__avatar">
            <img src="{{ $message->avatarUrl() }}" alt="{{ $message->displayName() }}">
        </div>
        <div class="support-chat-bubble__body">
            <div class="support-chat-bubble__meta">
                <strong>{{ $message->displayName() }}</strong>
                <span>{{ optional($message->created_at)->format('H:i') }}</span>
            </div>
            <div class="support-chat-bubble__text">
                {!! nl2br(e($message->body)) !!}
            </div>
        </div>
    </article>
@empty
    @unless($itemsOnly ?? false)
        <div class="support-chat-empty">
            <h3>{{ __('support_chat::messages.empty_thread_title') }}</h3>
            <p>{{ __('support_chat::messages.empty_thread_body') }}</p>
        </div>
    @endunless
@endforelse

@unless($itemsOnly ?? false)
    </div>
@endunless
