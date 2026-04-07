@php
    $messages = $messages ?? collect();
@endphp

@unless($itemsOnly ?? false)
    <div class="support-chat-widget__thread">
@endunless

@forelse($messages as $message)
    @php
        $isMine = $message->sender_type !== 'admin';
    @endphp
    <article class="support-chat-widget__bubble {{ $isMine ? 'is-mine' : 'is-support' }}" data-message-id="{{ $message->id }}">
        <div class="support-chat-widget__bubble-meta">
            <strong>{{ $message->displayName() }}</strong>
            <span>{{ optional($message->created_at)->format('H:i') }}</span>
        </div>
        <p>{{ $message->body }}</p>
    </article>
@empty
    @unless($itemsOnly ?? false)
        <div class="support-chat-widget__empty">
            <strong>{{ __('support_chat::messages.widget_empty_title') }}</strong>
            <p>{{ __('support_chat::messages.widget_empty_body') }}</p>
        </div>
    @endunless
@endforelse

@unless($itemsOnly ?? false)
    </div>
@endunless
