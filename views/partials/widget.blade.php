@php
    $isLocalMode = ($channel_mode ?? 'local') === 'local';
    $widgetId = 'support-chat-widget-' . substr(md5((string) url()->current()), 0, 8);
    $pageTitle = trim($__env->yieldContent('title')) !== '' ? trim($__env->yieldContent('title')) : config('app.name');
@endphp

@if($isLocalMode)
    <div
        class="support-chat-widget"
        id="{{ $widgetId }}"
        data-support-chat-widget
        data-thread-url="{{ $thread_url }}"
        data-poll-url="{{ $poll_url }}"
        data-start-url="{{ $start_url }}"
        data-message-url="{{ $message_url }}"
        data-is-authenticated="{{ auth()->check() ? '1' : '0' }}"
        data-page-url="{{ request()->fullUrl() }}"
        data-page-title="{{ $pageTitle }}"
        data-request-failed="{{ __('support_chat::messages.request_failed') }}"
    >
        <button type="button" class="support-chat-widget__trigger" data-support-chat-toggle aria-expanded="false" aria-controls="{{ $widgetId }}-panel">
            <i class="fa-solid fa-comments" aria-hidden="true"></i>
            <span>{{ __('support_chat::messages.open_chat_label') }}</span>
        </button>

        <section class="support-chat-widget__panel" id="{{ $widgetId }}-panel" hidden>
            <header class="support-chat-widget__header">
                <div>
                    <strong>{{ $settings['team_name'] ?? __('support_chat::messages.support_team_label') }}</strong>
                    <p>{{ $settings['welcome_message'] ?? '' }}</p>
                </div>
                <button type="button" class="support-chat-widget__close" data-support-chat-close aria-label="{{ __('support_chat::messages.close_label') }}">
                    <i class="fa fa-times" aria-hidden="true"></i>
                </button>
            </header>

            <div class="support-chat-widget__body">
                <div class="support-chat-widget__messages" data-support-chat-messages></div>

                <form class="support-chat-widget__form" data-support-chat-form>
                    @csrf
                    <div class="support-chat-widget__guest-fields" data-support-chat-guest-fields @if(auth()->check()) hidden @endif>
                        <input type="text" name="guest_name" class="form-control" placeholder="{{ __('support_chat::messages.guest_name_placeholder') }}">
                        <input type="email" name="guest_email" class="form-control" placeholder="{{ __('support_chat::messages.guest_email_placeholder') }}">
                    </div>
                    <textarea name="message" class="form-control" rows="3" placeholder="{{ __('support_chat::messages.public_message_placeholder') }}"></textarea>
                    <p class="support-chat-widget__error" data-support-chat-error></p>
                    <button type="submit" class="btn btn-success support-chat-widget__submit">{{ __('support_chat::messages.send_message_label') }}</button>
                </form>
            </div>
        </section>
    </div>
    <script>
@if(\Illuminate\Support\Facades\File::exists(base_path('plugins/support-chat/assets/support-chat.js')))
{!! \Illuminate\Support\Facades\File::get(base_path('plugins/support-chat/assets/support-chat.js')) !!}
@endif
    </script>
@else
    <a
        href="{{ $external_url }}"
        target="_blank"
        rel="noopener"
        class="support-chat-widget support-chat-widget--external"
        data-support-chat-external
        aria-label="{{ __('support_chat::messages.open_selected_channel') }}"
    >
        <span class="support-chat-widget__trigger support-chat-widget__trigger--external">
            <i class="{{ ($channel_mode ?? '') === 'messenger' ? 'fab fa-facebook-messenger' : 'fab fa-whatsapp' }}" aria-hidden="true"></i>
            <span>{{ __('support_chat::messages.open_selected_channel') }}</span>
        </span>
    </a>
@endif
