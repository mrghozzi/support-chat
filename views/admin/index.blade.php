@extends('admin::layouts.admin')

@section('title', __('support_chat::messages.support_chat_title'))
@section('admin_shell_header_mode', 'hidden')

@php
    $activeStatus = $activeThread?->status ?? 'open';
    $filters = [
        'open' => __('support_chat::messages.filter_open'),
        'awaiting_reply' => __('support_chat::messages.filter_awaiting_reply'),
        'closed' => __('support_chat::messages.filter_closed'),
    ];
@endphp

@section('content')
<link rel="stylesheet" href="{{ asset('plugin-assets/support-chat/admin-support-chat.css') }}">

<div class="support-chat-admin-shell">
    <section class="support-chat-admin-hero card stretch stretch-full">
        <div class="card-body">
            <div class="support-chat-admin-hero__copy">
                <span class="support-chat-admin-hero__eyebrow">{{ __('support_chat::messages.support_chat_title') }}</span>
                <h1 class="support-chat-admin-hero__title">{{ __('support_chat::messages.support_chat_intro') }}</h1>
                <p class="support-chat-admin-hero__text">{{ __('support_chat::messages.support_chat_admin_blurb') }}</p>
            </div>
            <div class="support-chat-admin-hero__meta">
                <div class="support-chat-admin-stat">
                    <span class="support-chat-admin-stat__label">{{ __('support_chat::messages.active_channel_label') }}</span>
                    <strong class="support-chat-admin-stat__value">{{ __('support_chat::messages.channel_' . ($settings['channel_mode'] ?? 'local')) }}</strong>
                </div>
                <div class="support-chat-admin-stat">
                    <span class="support-chat-admin-stat__label">{{ __('support_chat::messages.widget_status_label') }}</span>
                    <strong class="support-chat-admin-stat__value">{{ !empty($settings['widget_enabled']) ? __('support_chat::messages.enabled_label') : __('support_chat::messages.disabled_label') }}</strong>
                </div>
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="alert alert-success support-chat-admin-alert">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger support-chat-admin-alert">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger support-chat-admin-alert">{{ $errors->first() }}</div>
    @endif

    @if(($settings['channel_mode'] ?? 'local') === 'local')
        @if(!$schemaReady && $setupNotice)
            <div class="alert alert-warning support-chat-admin-alert support-chat-admin-alert--setup">
                <strong>{{ $setupNotice['title'] }}</strong>
                <span>{{ $setupNotice['message'] }}</span>
                <span class="d-block mt-1">{{ __('support_chat::messages.manual_migration_hint') }}</span>
            </div>
        @endif

        @if($schemaReady)
            <section class="support-chat-workspace" data-support-chat-admin>
                <aside class="support-chat-rail card">
                    <div class="support-chat-rail__header">
                        <h2>{{ __('support_chat::messages.inbox_title') }}</h2>
                        <p>{{ __('support_chat::messages.inbox_subtitle') }}</p>
                    </div>

                    <form method="GET" action="{{ route('admin.support_chat.index') }}" class="support-chat-rail__search">
                        <input type="hidden" name="filter" value="{{ $filter }}">
                        <input
                            type="search"
                            name="search"
                            value="{{ $search }}"
                            class="form-control"
                            placeholder="{{ __('support_chat::messages.search_placeholder') }}"
                        >
                    </form>

                    <div class="support-chat-rail__filters">
                        @foreach($filters as $filterKey => $filterLabel)
                            <a
                                href="{{ route('admin.support_chat.index', ['filter' => $filterKey, 'search' => $search]) }}"
                                class="support-chat-filter {{ $filter === $filterKey ? 'is-active' : '' }}"
                            >
                                {{ $filterLabel }}
                            </a>
                        @endforeach
                    </div>

                    <div class="support-chat-thread-list">
                        @forelse($threads as $thread)
                            <a
                                href="{{ route('admin.support_chat.index', ['filter' => $filter, 'search' => $search, 'thread' => $thread->id]) }}"
                                class="support-chat-thread-card {{ $activeThread && (int) $activeThread->id === (int) $thread->id ? 'is-active' : '' }}"
                            >
                                <div class="support-chat-thread-card__avatar">
                                    <img src="{{ $thread->participantAvatarUrl() }}" alt="{{ $thread->participantName() }}">
                                </div>
                                <div class="support-chat-thread-card__body">
                                    <div class="support-chat-thread-card__topline">
                                        <strong>{{ $thread->participantName() }}</strong>
                                        <span>{{ optional($thread->last_message_at)->diffForHumans() ?? __('support_chat::messages.just_now_label') }}</span>
                                    </div>
                                    <p>{{ $thread->lastPreview() }}</p>
                                    <div class="support-chat-thread-card__meta">
                                        <span class="support-chat-pill support-chat-pill--status status-{{ $thread->status }}">{{ __('support_chat::messages.status_' . $thread->status) }}</span>
                                        @if(($thread->admin_unread_count ?? 0) > 0)
                                            <span class="support-chat-pill support-chat-pill--unread">{{ $thread->admin_unread_count }}</span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="support-chat-empty support-chat-empty--rail">
                                <h3>{{ __('support_chat::messages.empty_inbox_title') }}</h3>
                                <p>{{ __('support_chat::messages.empty_inbox_body') }}</p>
                            </div>
                        @endforelse
                    </div>

                    @if(method_exists($threads, 'links'))
                        <div class="support-chat-rail__footer">
                            {{ $threads->links() }}
                        </div>
                    @endif
                </aside>

                <section class="support-chat-conversation card">
                    @if($activeThread)
                        <header class="support-chat-conversation__header">
                            <div>
                                <span class="support-chat-conversation__eyebrow">{{ __('support_chat::messages.live_thread_label') }}</span>
                                <h2>{{ $activeThread->participantName() }}</h2>
                                <p>{{ $activeThread->participantEmail() ?: __('support_chat::messages.no_email_label') }}</p>
                            </div>
                            <div class="support-chat-conversation__actions">
                                <a href="#support-chat-admin-message" class="btn btn-sm btn-outline-light d-none d-md-inline-flex align-items-center gap-2">
                                    <i class="feather-corner-up-left"></i>
                                    {{ __('support_chat::messages.reply_label') }}
                                </a>
                                <span class="support-chat-pill support-chat-pill--status status-{{ $activeStatus }}" data-thread-status-label>
                                    {{ __('support_chat::messages.status_' . $activeStatus) }}
                                </span>
                            </div>
                        </header>

                        <div class="support-chat-transcript" id="support-chat-admin-messages" data-latest-id="{{ (int) ($messages->last()->id ?? 0) }}">
                            @include('support_chat::admin.partials.messages', [
                                'messages' => $messages,
                                'activeThread' => $activeThread,
                                'currentAdminId' => $currentAdminId,
                                'itemsOnly' => false,
                            ])
                        </div>

                        <form
                            class="support-chat-compose"
                            data-support-chat-reply-form
                            action="{{ route('admin.support_chat.threads.reply', $activeThread->id) }}"
                            data-poll-url="{{ route('admin.support_chat.threads.poll', $activeThread->id) }}"
                        >
                            @csrf
                            <label class="form-label" for="support-chat-admin-message">{{ __('support_chat::messages.reply_label') }}</label>
                            <textarea id="support-chat-admin-message" name="message" class="form-control" rows="4" placeholder="{{ __('support_chat::messages.reply_placeholder') }}"></textarea>
                            <div class="support-chat-compose__footer">
                                <p class="support-chat-compose__error" data-support-chat-error></p>
                                <button type="submit" class="btn btn-primary">{{ __('support_chat::messages.send_reply_label') }}</button>
                            </div>
                        </form>
                    @else
                        <div class="support-chat-empty support-chat-empty--conversation">
                            <div class="support-chat-empty__icon">
                                <i class="feather-message-square"></i>
                            </div>
                            <h3>{{ __('support_chat::messages.no_thread_selected_title') }}</h3>
                            <p>{{ __('support_chat::messages.no_thread_selected_body') }}</p>
                            <div class="mt-4">
                                <span class="support-chat-pill">{{ __('support_chat::messages.select_thread_hint') }}</span>
                            </div>
                        </div>
                    @endif
                </section>

                <aside class="support-chat-sidebar card">
                    <div class="support-chat-sidebar__section">
                        <span class="support-chat-sidebar__eyebrow">{{ __('support_chat::messages.thread_details_title') }}</span>
                        @if($activeThread)
                            <div class="support-chat-detail-grid">
                                <div>
                                    <label>{{ __('support_chat::messages.visitor_label') }}</label>
                                    <strong>{{ $activeThread->participantName() }}</strong>
                                </div>
                                <div>
                                    <label>{{ __('support_chat::messages.email_label') }}</label>
                                    <strong>{{ $activeThread->participantEmail() ?: __('support_chat::messages.no_email_label') }}</strong>
                                </div>
                                <div>
                                    <label>{{ __('support_chat::messages.started_from_label') }}</label>
                                    <strong>{{ $activeThread->started_page_title ?: __('support_chat::messages.unknown_page_label') }}</strong>
                                </div>
                                <div>
                                    <label>{{ __('support_chat::messages.latest_activity_label') }}</label>
                                    <strong>{{ optional($activeThread->last_message_at)->diffForHumans() ?? __('support_chat::messages.just_now_label') }}</strong>
                                </div>
                            </div>

                            @if($activeThread->started_page_url)
                                <a href="{{ $activeThread->started_page_url }}" target="_blank" rel="noopener" class="support-chat-sidebar__link">
                                    {{ __('support_chat::messages.open_origin_page') }}
                                </a>
                            @endif

                            <form
                                class="support-chat-sidebar__control"
                                data-support-chat-assign-form
                                action="{{ route('admin.support_chat.threads.assign', $activeThread->id) }}"
                            >
                                @csrf
                                <label class="form-label" for="assigned_admin_id">{{ __('support_chat::messages.assign_label') }}</label>
                                <select class="form-select" id="assigned_admin_id" name="assigned_admin_id">
                                    <option value="">{{ __('support_chat::messages.unassigned_label') }}</option>
                                    @foreach($assignees as $assignee)
                                        <option value="{{ $assignee->id }}" @selected((int) ($activeThread->assigned_admin_id ?? 0) === (int) $assignee->id)>
                                            {{ $assignee->username }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>

                            <div class="support-chat-sidebar__control">
                                <label class="form-label">{{ __('support_chat::messages.status_label') }}</label>
                                <div class="support-chat-status-actions" data-support-chat-status-group data-action="{{ route('admin.support_chat.threads.status', $activeThread->id) }}">
                                    @csrf
                                    @foreach(['open', 'pending', 'closed'] as $statusOption)
                                        <button
                                            type="button"
                                            class="btn btn-sm {{ $activeStatus === $statusOption ? 'btn-primary' : 'btn-outline-light' }}"
                                            data-status="{{ $statusOption }}"
                                        >
                                            {{ __('support_chat::messages.status_' . $statusOption) }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="support-chat-empty support-chat-empty--sidebar">
                                <h3>{{ __('support_chat::messages.no_thread_selected_title') }}</h3>
                                <p>{{ __('support_chat::messages.select_thread_hint') }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="support-chat-sidebar__section">
                        <span class="support-chat-sidebar__eyebrow">{{ __('support_chat::messages.channel_summary_title') }}</span>
                        <div class="support-chat-channel-card">
                            <strong>{{ __('support_chat::messages.channel_' . ($settings['channel_mode'] ?? 'local')) }}</strong>
                            <p>{{ __('support_chat::messages.current_channel_summary') }}</p>
                        </div>
                    </div>
                </aside>
            </section>
        @endif
    @else
        <section class="support-chat-external-grid">
            <div class="card">
                <div class="card-body">
                    <span class="support-chat-sidebar__eyebrow">{{ __('support_chat::messages.external_preview_title') }}</span>
                    <h2 class="support-chat-preview__title">{{ __('support_chat::messages.channel_' . ($settings['channel_mode'] ?? 'whatsapp')) }}</h2>
                    <p class="support-chat-preview__text">{{ __('support_chat::messages.external_preview_blurb') }}</p>
                    @if($externalUrl)
                        <a href="{{ $externalUrl }}" target="_blank" rel="noopener" class="btn btn-primary">
                            {{ __('support_chat::messages.open_selected_channel') }}
                        </a>
                    @else
                        <p class="support-chat-preview__missing">{{ __('support_chat::messages.external_channel_missing') }}</p>
                    @endif
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <span class="support-chat-sidebar__eyebrow">{{ __('support_chat::messages.external_mode_title') }}</span>
                    <h2 class="support-chat-preview__title">{{ __('support_chat::messages.settings_title') }}</h2>
                    <p class="support-chat-preview__text">{{ __('support_chat::messages.external_mode_body') }}</p>
                </div>
            </div>
        </section>
    @endif

    <section class="card support-chat-settings-card">
        <div class="card-body">
            <div class="support-chat-settings-card__header">
                <div>
                    <span class="support-chat-sidebar__eyebrow">{{ __('support_chat::messages.settings_title') }}</span>
                    <h2>{{ __('support_chat::messages.settings_subtitle') }}</h2>
                </div>
                <p>{{ __('support_chat::messages.settings_help') }}</p>
            </div>

            <form method="POST" action="{{ route('admin.support_chat.settings.update') }}" class="support-chat-settings-form">
                @csrf
                <div class="support-chat-settings-form__grid">
                    <div class="support-chat-field">
                        <label class="form-label" for="channel_mode">{{ __('support_chat::messages.channel_mode_label') }}</label>
                        <select class="form-select" id="channel_mode" name="channel_mode">
                            <option value="local" @selected(($settings['channel_mode'] ?? 'local') === 'local')>{{ __('support_chat::messages.channel_local') }}</option>
                            <option value="whatsapp" @selected(($settings['channel_mode'] ?? '') === 'whatsapp')>{{ __('support_chat::messages.channel_whatsapp') }}</option>
                            <option value="messenger" @selected(($settings['channel_mode'] ?? '') === 'messenger')>{{ __('support_chat::messages.channel_messenger') }}</option>
                        </select>
                    </div>

                    <div class="support-chat-field support-chat-field--toggle">
                        <label class="form-label" for="widget_enabled">{{ __('support_chat::messages.widget_enabled_label') }}</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="widget_enabled" name="widget_enabled" value="1" @checked(!empty($settings['widget_enabled']))>
                            <label class="form-check-label" for="widget_enabled">{{ __('support_chat::messages.show_widget_label') }}</label>
                        </div>
                    </div>

                    <div class="support-chat-field">
                        <label class="form-label" for="team_name">{{ __('support_chat::messages.team_name_label') }}</label>
                        <input type="text" class="form-control" id="team_name" name="team_name" value="{{ old('team_name', $settings['team_name'] ?? '') }}">
                    </div>

                    <div class="support-chat-field">
                        <label class="form-label" for="welcome_message">{{ __('support_chat::messages.welcome_message_label') }}</label>
                        <textarea class="form-control" id="welcome_message" name="welcome_message" rows="3">{{ old('welcome_message', $settings['welcome_message'] ?? '') }}</textarea>
                    </div>

                    <div class="support-chat-field">
                        <label class="form-label" for="whatsapp_number">{{ __('support_chat::messages.whatsapp_number_label') }}</label>
                        <input type="text" class="form-control" id="whatsapp_number" name="whatsapp_number" value="{{ old('whatsapp_number', $settings['whatsapp_number'] ?? '') }}">
                    </div>

                    <div class="support-chat-field">
                        <label class="form-label" for="whatsapp_prefill">{{ __('support_chat::messages.whatsapp_prefill_label') }}</label>
                        <textarea class="form-control" id="whatsapp_prefill" name="whatsapp_prefill" rows="3">{{ old('whatsapp_prefill', $settings['whatsapp_prefill'] ?? '') }}</textarea>
                    </div>

                    <div class="support-chat-field">
                        <label class="form-label" for="messenger_url">{{ __('support_chat::messages.messenger_url_label') }}</label>
                        <input type="url" class="form-control" id="messenger_url" name="messenger_url" value="{{ old('messenger_url', $settings['messenger_url'] ?? '') }}">
                    </div>

                    <div class="support-chat-field">
                        <label class="form-label" for="visibility_rules">{{ __('support_chat::messages.visibility_rules_label') }}</label>
                        <textarea class="form-control" id="visibility_rules" name="visibility_rules" rows="4" placeholder="admin*&#10;login*">{{ old('visibility_rules', $settings['visibility_rules'] ?? '') }}</textarea>
                        <small>{{ __('support_chat::messages.visibility_rules_help') }}</small>
                    </div>
                </div>

                <div class="support-chat-settings-form__footer">
                    <button type="submit" class="btn btn-primary">{{ __('support_chat::messages.save_settings_label') }}</button>
                </div>
            </form>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('plugin-assets/support-chat/admin-support-chat.js') }}"></script>
@endpush
