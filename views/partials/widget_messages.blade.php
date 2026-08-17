@php
    $messages = $messages ?? collect();
@endphp

@unless($itemsOnly ?? false)
    <div class="support-chat-widget__thread">
@endunless

@forelse($messages as $message)
    @php
        $isMine = in_array($message->sender_type, ['guest', 'member'], true);
        $isAi = in_array($message->sender_type, ['ai', 'bot'], true);
        $bubbleClass = $isMine ? 'is-mine' : ($isAi ? 'is-support is-ai' : 'is-support');
    @endphp
    <article class="support-chat-widget__bubble {{ $bubbleClass }}" data-message-id="{{ $message->id }}">
        <div class="support-chat-widget__bubble-meta">
            <div style="display: flex; align-items: center; gap: 4px;">
                <strong>{{ $message->displayName() }}</strong>
                @if($isAi)
                    <span style="font-size: 10px; background: rgba(97, 93, 250, 0.15); color: #615dfa; padding: 2px 6px; border-radius: 99px; font-weight: 600;">🤖 AI</span>
                @endif
            </div>
            <span>{{ optional($message->created_at)->format('H:i') }}</span>
        </div>
        <p>{!! nl2br(e($message->body)) !!}</p>
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
