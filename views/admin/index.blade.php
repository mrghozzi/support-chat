@extends('admin::layouts.admin')

@section('title', __('support_chat::messages.support_chat_title'))

@php
    $activeStatus = $activeThread?->status ?? 'open';
    $activeTab = request()->query('tab', 'chat');
    if (!in_array($activeTab, ['chat', 'ai', 'channels'], true)) {
        $activeTab = 'chat';
    }
    $filters = [
        'all' => __('support_chat::messages.filter_all'),
        'open' => __('support_chat::messages.filter_open'),
        'awaiting_reply' => __('support_chat::messages.filter_awaiting_reply'),
        'closed' => __('support_chat::messages.filter_closed'),
    ];
    $currentProvider = (string) ($settings['ai_provider'] ?? 'pollinations');
@endphp

@section('content')
<link rel="stylesheet" href="{{ route('support_chat.asset', ['path' => 'admin-support-chat.css']) }}">

<div class="support-chat-admin-shell">
    {{-- Superdesign Admin Hero --}}
    <section class="admin-hero support-chat-hero">
        <div class="admin-hero__content">
            <ul class="admin-breadcrumb">
                <li><a href="{{ route('admin.index') }}">{{ __('messages.dashboard') }}</a></li>
                <li><a href="{{ route('admin.plugins') }}">{{ __('messages.plugins') }}</a></li>
                <li>{{ __('support_chat::messages.support_chat_title') }}</li>
            </ul>
            <div class="admin-hero__eyebrow">
                <i class="feather-message-square me-1"></i> {{ __('support_chat::messages.support_chat_title') }}
            </div>
            <h1 class="admin-hero__title">{{ __('support_chat::messages.support_chat_intro') }}</h1>
            <p class="admin-hero__copy">{{ __('support_chat::messages.support_chat_admin_blurb') }}</p>
        </div>
        <div class="admin-hero__actions">
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if(!empty($settings['ai_enabled']))
                    <span class="badge bg-soft-success text-success p-2 px-3 rounded-pill d-inline-flex align-items-center gap-1">
                        <span class="pulse-dot bg-success"></span>
                        <i class="feather-cpu"></i> AI {{ strtoupper($currentProvider) }}: {{ __('support_chat::messages.ai_mode_' . ($settings['ai_mode'] ?? 'auto_reply')) }}
                    </span>
                @else
                    <span class="badge bg-soft-warning text-warning p-2 px-3 rounded-pill d-inline-flex align-items-center gap-1">
                        <i class="feather-pause-circle"></i> AI: {{ __('support_chat::messages.disabled_label') }}
                    </span>
                @endif
                <span class="badge bg-soft-primary text-primary p-2 px-3 rounded-pill d-inline-flex align-items-center gap-1">
                    <i class="feather-radio"></i> {{ __('support_chat::messages.channel_' . ($settings['channel_mode'] ?? 'local')) }}
                </span>
            </div>
        </div>
    </section>

    {{-- Flash Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="feather-check-circle fs-4 me-2"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="feather-alert-triangle fs-4 me-2"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="feather-alert-circle fs-4 me-2"></i>
                <span>{{ $errors->first() }}</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- KPI Metric Summary Cards --}}
    <section class="row g-3 mb-4 support-chat-stats-row">
        <div class="col-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm support-chat-kpi-card">
                <div class="card-body d-flex align-items-center justify-content-between p-3 p-lg-4">
                    <div>
                        <span class="text-muted small text-uppercase fw-bold">{{ __('support_chat::messages.kpi_total_threads') }}</span>
                        <h3 class="mb-0 fw-bold mt-1">{{ number_format($stats['total_threads'] ?? 0) }}</h3>
                    </div>
                    <div class="bg-soft-primary text-primary rounded-4 p-3 support-chat-kpi-icon">
                        <i class="feather-message-circle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm support-chat-kpi-card">
                <div class="card-body d-flex align-items-center justify-content-between p-3 p-lg-4">
                    <div>
                        <span class="text-muted small text-uppercase fw-bold">{{ __('support_chat::messages.kpi_awaiting_reply') }}</span>
                        <h3 class="mb-0 fw-bold mt-1 text-warning">{{ number_format($stats['awaiting_reply'] ?? 0) }}</h3>
                    </div>
                    <div class="bg-soft-warning text-warning rounded-4 p-3 support-chat-kpi-icon">
                        <i class="feather-clock fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm support-chat-kpi-card">
                <div class="card-body d-flex align-items-center justify-content-between p-3 p-lg-4">
                    <div>
                        <span class="text-muted small text-uppercase fw-bold">{{ __('support_chat::messages.kpi_ai_replies') }}</span>
                        <h3 class="mb-0 fw-bold mt-1 text-success">{{ number_format($stats['ai_messages_count'] ?? 0) }}</h3>
                    </div>
                    <div class="bg-soft-success text-success rounded-4 p-3 support-chat-kpi-icon">
                        <i class="feather-cpu fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm support-chat-kpi-card">
                <div class="card-body d-flex align-items-center justify-content-between p-3 p-lg-4">
                    <div>
                        <span class="text-muted small text-uppercase fw-bold">{{ __('support_chat::messages.kpi_active_provider') }}</span>
                        <h4 class="mb-0 fw-bold mt-1 text-info text-capitalize">{{ $stats['ai_provider'] ?? 'Pollinations' }}</h4>
                    </div>
                    <div class="bg-soft-info text-info rounded-4 p-3 support-chat-kpi-icon">
                        <i class="feather-zap fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Main Tabs Navigation --}}
    <div class="support-chat-tabs mb-4">
        <ul class="nav nav-pills support-chat-nav-pills p-1 bg-white dark-surface rounded-4 shadow-sm" role="tablist">
            <li class="nav-item flex-fill text-center" role="presentation">
                <button
                    class="nav-link w-100 py-3 rounded-4 {{ $activeTab === 'chat' ? 'active' : '' }}"
                    id="tab-chat-btn"
                    data-bs-toggle="pill"
                    data-bs-target="#tab-chat-pane"
                    type="button"
                    role="tab"
                    aria-selected="{{ $activeTab === 'chat' ? 'true' : 'false' }}"
                >
                    <i class="feather-message-square me-2"></i>
                    <span class="fw-bold">{{ __('support_chat::messages.tab_chat') }}</span>
                    @if(($stats['awaiting_reply'] ?? 0) > 0)
                        <span class="badge bg-danger rounded-pill ms-2">{{ $stats['awaiting_reply'] }}</span>
                    @endif
                </button>
            </li>
            <li class="nav-item flex-fill text-center" role="presentation">
                <button
                    class="nav-link w-100 py-3 rounded-4 {{ $activeTab === 'ai' ? 'active' : '' }}"
                    id="tab-ai-btn"
                    data-bs-toggle="pill"
                    data-bs-target="#tab-ai-pane"
                    type="button"
                    role="tab"
                    aria-selected="{{ $activeTab === 'ai' ? 'true' : 'false' }}"
                >
                    <i class="feather-cpu me-2"></i>
                    <span class="fw-bold">{{ __('support_chat::messages.tab_ai_settings') }}</span>
                    <span class="badge bg-soft-info text-info rounded-pill ms-2">4 AI Providers</span>
                </button>
            </li>
            <li class="nav-item flex-fill text-center" role="presentation">
                <button
                    class="nav-link w-100 py-3 rounded-4 {{ $activeTab === 'channels' ? 'active' : '' }}"
                    id="tab-channels-btn"
                    data-bs-toggle="pill"
                    data-bs-target="#tab-channels-pane"
                    type="button"
                    role="tab"
                    aria-selected="{{ $activeTab === 'channels' ? 'true' : 'false' }}"
                >
                    <i class="feather-sliders me-2"></i>
                    <span class="fw-bold">{{ __('support_chat::messages.tab_channels') }}</span>
                </button>
            </li>
        </ul>
    </div>

    {{-- Tab Content Panes --}}
    <div class="tab-content">
        {{-- ================= TAB 1: LIVE INBOX ================= --}}
        <div class="tab-pane fade {{ $activeTab === 'chat' ? 'show active' : '' }}" id="tab-chat-pane" role="tabpanel" aria-labelledby="tab-chat-btn">
            @if(($settings['channel_mode'] ?? 'local') === 'local')
                @if(!$schemaReady && $setupNotice)
                    <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4">
                        <div class="d-flex align-items-start gap-3">
                            <div class="bg-soft-warning text-warning p-2 rounded-circle"><i class="feather-alert-triangle fs-3"></i></div>
                            <div>
                                <h5 class="fw-bold mb-1">{{ $setupNotice['title'] }}</h5>
                                <p class="mb-2 text-muted">{{ $setupNotice['message'] }}</p>
                                <span class="badge bg-warning text-dark">{{ __('support_chat::messages.manual_migration_hint') }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                @if($schemaReady)
                    <section class="support-chat-workspace" data-support-chat-admin>
                        {{-- Left Column: Threads Rail --}}
                        <aside class="support-chat-rail card border-0 shadow-sm rounded-4">
                            <div class="support-chat-rail__header p-3 border-bottom">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h5 class="fw-bold mb-0 text-dark dark-text">{{ __('support_chat::messages.inbox_title') }}</h5>
                                    <span class="badge bg-soft-primary text-primary rounded-pill">{{ $threads->total() ?? $threads->count() }}</span>
                                </div>
                                <p class="text-muted small mb-0">{{ __('support_chat::messages.inbox_subtitle') }}</p>
                            </div>

                            <div class="p-3 border-bottom">
                                <form method="GET" action="{{ route('admin.support_chat.index') }}" class="support-chat-rail__search position-relative">
                                    <input type="hidden" name="tab" value="chat">
                                    <input type="hidden" name="filter" value="{{ $filter }}">
                                    <i class="feather-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                                    <input
                                        type="search"
                                        name="q"
                                        value="{{ $search }}"
                                        class="form-control ps-5 pe-4 rounded-3 border-light bg-light"
                                        placeholder="{{ __('support_chat::messages.search_placeholder') }}"
                                    >
                                </form>
                            </div>

                            <div class="support-chat-rail__filters p-3 border-bottom d-flex flex-wrap gap-1">
                                @foreach($filters as $filterKey => $filterLabel)
                                    <a
                                        href="{{ route('admin.support_chat.index', ['tab' => 'chat', 'filter' => $filterKey, 'q' => $search]) }}"
                                        class="btn btn-sm {{ ($filter === $filterKey || ($filterKey === 'all' && empty($filter))) ? 'btn-primary' : 'btn-light border-0' }} rounded-pill px-3"
                                    >
                                        {{ $filterLabel }}
                                    </a>
                                @endforeach
                            </div>

                            <div class="support-chat-thread-list p-2">
                                @forelse($threads as $thread)
                                    <a
                                        href="{{ route('admin.support_chat.index', ['tab' => 'chat', 'filter' => $filter, 'q' => $search, 'thread' => $thread->id]) }}"
                                        class="support-chat-thread-card p-3 mb-2 rounded-3 d-flex align-items-start gap-3 {{ $activeThread && (int) $activeThread->id === (int) $thread->id ? 'is-active bg-soft-primary border-primary' : 'bg-transparent' }}"
                                    >
                                        <div class="support-chat-avatar-hex-wrap {{ $thread->isGuestThread() ? 'is-guest-avatar' : 'is-user-avatar' }} flex-shrink-0">
                                            <img src="{{ $thread->participantAvatarUrl() }}" alt="{{ $thread->participantName() }}" class="support-chat-avatar-hex-img">
                                        </div>
                                        <div class="support-chat-thread-card__body flex-grow-1 min-w-0">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <strong class="text-truncate text-dark dark-text fs-14">{{ $thread->participantName() }}</strong>
                                                <span class="text-muted fs-11 flex-shrink-0">{{ optional($thread->last_message_at)->diffForHumans() ?? __('support_chat::messages.just_now_label') }}</span>
                                            </div>
                                            <p class="text-muted small text-truncate mb-2">{{ $thread->lastPreview() }}</p>
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="badge status-badge status-{{ $thread->status }} fs-11">{{ __('support_chat::messages.status_' . $thread->status) }}</span>
                                                @if(($thread->admin_unread_count ?? 0) > 0)
                                                    <span class="badge bg-danger rounded-pill fs-11">{{ $thread->admin_unread_count }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </a>
                                @empty
                                    <div class="support-chat-empty support-chat-empty--rail text-center py-5">
                                        <div class="bg-soft-primary text-primary rounded-circle d-inline-flex p-3 mb-3">
                                            <i class="feather-inbox fs-3"></i>
                                        </div>
                                        <h6 class="fw-bold mb-1">{{ __('support_chat::messages.empty_inbox_title') }}</h6>
                                        <p class="text-muted small">{{ __('support_chat::messages.empty_inbox_body') }}</p>
                                    </div>
                                @endforelse
                            </div>

                            @if(method_exists($threads, 'links') && $threads->hasPages())
                                <div class="support-chat-rail__footer p-3 border-top">
                                    {{ $threads->links() }}
                                </div>
                            @endif
                        </aside>

                        {{-- Middle Column: Conversation Stream --}}
                        <section class="support-chat-conversation card border-0 shadow-sm rounded-4">
                            @if($activeThread)
                                <header class="support-chat-conversation__header p-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="support-chat-avatar-hex-wrap {{ $activeThread->isGuestThread() ? 'is-guest-avatar' : 'is-user-avatar' }} flex-shrink-0">
                                            <img src="{{ $activeThread->participantAvatarUrl() }}" alt="{{ $activeThread->participantName() }}" class="support-chat-avatar-hex-img">
                                        </div>
                                        <div>
                                            <div class="d-flex align-items-center gap-2">
                                                <h5 class="fw-bold mb-0 text-dark dark-text">{{ $activeThread->participantName() }}</h5>
                                                @if($activeThread->isGuestThread())
                                                    <span class="badge bg-soft-secondary text-secondary rounded-pill fs-11">{{ __('support_chat::messages.guest_badge') }}</span>
                                                @else
                                                    <span class="badge bg-soft-success text-success rounded-pill fs-11">{{ __('support_chat::messages.member_badge') }}</span>
                                                @endif
                                            </div>
                                            <span class="text-muted small">{{ $activeThread->participantEmail() ?: __('support_chat::messages.no_email_label') }}</span>
                                        </div>
                                    </div>
                                    <div class="support-chat-conversation__actions d-flex align-items-center gap-2">
                                        <span class="badge status-badge status-{{ $activeStatus }} fs-12 px-3 py-2" data-thread-status-label>
                                            {{ __('support_chat::messages.status_' . $activeStatus) }}
                                        </span>
                                    </div>
                                </header>

                                <div class="support-chat-transcript p-4" id="support-chat-admin-messages" data-latest-id="{{ (int) ($messages->last()->id ?? 0) }}">
                                    @include('support_chat::admin.partials.messages', [
                                        'messages' => $messages,
                                        'activeThread' => $activeThread,
                                        'currentAdminId' => $currentAdminId,
                                        'itemsOnly' => false,
                                    ])
                                </div>

                                {{-- AI Suggestion Quick Bar --}}
                                <div class="support-chat-ai-bar p-3 bg-soft-light border-top border-bottom">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-soft-info text-info p-2 rounded-circle">
                                                <i class="feather-zap fs-5"></i>
                                            </div>
                                            <div>
                                                <strong class="fs-13 text-dark dark-text d-block">{{ __('support_chat::messages.ai_settings_title') }}</strong>
                                                <span class="text-muted fs-11">{{ __('support_chat::messages.provider_' . $currentProvider) }}</span>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-info text-white rounded-pill px-3 d-inline-flex align-items-center gap-2 shadow-sm"
                                            id="btn-ai-suggest"
                                            data-action="{{ route('admin.support_chat.threads.ai_suggest', $activeThread->id) }}"
                                        >
                                            <i class="feather-sparkles"></i>
                                            <span>{{ __('support_chat::messages.ai_suggest_reply') }}</span>
                                        </button>
                                    </div>
                                    <div class="ai-suggestion-box mt-3 p-3 bg-white dark-surface rounded-3 border shadow-sm d-none" id="ai-suggestion-box">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-soft-info text-info fs-11"><i class="feather-cpu me-1"></i>{{ __('support_chat::messages.ai_bot_default_name') }}</span>
                                                <span class="text-muted fs-11 fst-italic">{{ __('support_chat::messages.ai_suggest_reply') }}</span>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" id="btn-apply-suggestion">
                                                    <i class="feather-check me-1"></i> {{ __('support_chat::messages.apply_suggestion') }}
                                                </button>
                                                <button type="button" class="btn btn-sm btn-light border rounded-pill px-2" id="btn-dismiss-suggestion">
                                                    <i class="feather-x"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="p-2 bg-light rounded-3 text-dark dark-text fs-13 lh-base border mb-0" id="ai-suggestion-text"></div>
                                    </div>
                                </div>

                                {{-- Reply Form --}}
                                <form
                                    method="POST"
                                    class="support-chat-compose p-3 p-lg-4"
                                    data-support-chat-reply-form
                                    action="{{ route('admin.support_chat.threads.reply', $activeThread->id) }}"
                                    data-poll-url="{{ route('admin.support_chat.threads.poll', $activeThread->id) }}"
                                >
                                    @csrf
                                    <div class="position-relative">
                                        <textarea
                                            id="support-chat-admin-message"
                                            name="message"
                                            class="form-control rounded-3 border-light p-3 shadow-none"
                                            rows="3"
                                            placeholder="{{ __('support_chat::messages.reply_placeholder') }}"
                                        ></textarea>
                                    </div>
                                    <div class="support-chat-compose__footer d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-info rounded-pill px-3 d-inline-flex align-items-center gap-1"
                                                onclick="document.getElementById('btn-ai-suggest').click()"
                                            >
                                                <i class="feather-sparkles"></i>
                                                <span>{{ __('support_chat::messages.ai_suggest_reply') }}</span>
                                            </button>
                                            <p class="support-chat-compose__error text-danger mb-0 small" data-support-chat-error></p>
                                        </div>
                                        <button type="submit" class="btn btn-primary rounded-pill px-4 d-inline-flex align-items-center gap-2 shadow-sm">
                                            <i class="feather-send"></i>
                                            <span>{{ __('support_chat::messages.send_reply_label') }}</span>
                                        </button>
                                    </div>
                                </form>
                            @else
                                <div class="support-chat-empty support-chat-empty--conversation text-center py-5 my-auto">
                                    <div class="bg-soft-primary text-primary rounded-circle d-inline-flex p-4 mb-3">
                                        <i class="feather-message-square fs-1"></i>
                                    </div>
                                    <h4 class="fw-bold mb-2">{{ __('support_chat::messages.no_thread_selected_title') }}</h4>
                                    <p class="text-muted mb-4">{{ __('support_chat::messages.no_thread_selected_body') }}</p>
                                    <span class="badge bg-soft-info text-info p-2 px-3 rounded-pill">{{ __('support_chat::messages.select_conversation_prompt') }}</span>
                                </div>
                            @endif
                        </section>

                        {{-- Right Column: Thread Metadata & Controls Sidebar --}}
                        <aside class="support-chat-sidebar card border-0 shadow-sm rounded-4">
                            <div class="p-3 border-bottom">
                                <h6 class="fw-bold mb-0 text-dark dark-text">{{ __('support_chat::messages.thread_details_title') }}</h6>
                            </div>

                            @if($activeThread)
                                <div class="p-3 border-bottom text-center">
                                    <div class="support-chat-avatar-hex-wrap is-lg {{ $activeThread->isGuestThread() ? 'is-guest-avatar' : 'is-user-avatar' }} mx-auto mb-2">
                                        <img src="{{ $activeThread->participantAvatarUrl() }}" alt="{{ $activeThread->participantName() }}" class="support-chat-avatar-hex-img">
                                    </div>
                                    <h6 class="fw-bold mb-1 text-dark dark-text">{{ $activeThread->participantName() }}</h6>
                                    @if($activeThread->isGuestThread())
                                        <span class="badge bg-soft-secondary text-secondary rounded-pill fs-11">{{ __('support_chat::messages.guest_badge') }}</span>
                                    @else
                                        <span class="badge bg-soft-success text-success rounded-pill fs-11">{{ __('support_chat::messages.member_badge') }}</span>
                                    @endif
                                </div>

                                <div class="p-3 border-bottom">
                                    <div class="support-chat-detail-grid">
                                        <div class="mb-3">
                                            <label class="text-muted small text-uppercase fw-bold d-block mb-1">{{ __('support_chat::messages.email_label') }}</label>
                                            <strong class="text-dark dark-text fs-13">{{ $activeThread->participantEmail() ?: __('support_chat::messages.no_email_label') }}</strong>
                                        </div>
                                        <div class="mb-3">
                                            <label class="text-muted small text-uppercase fw-bold d-block mb-1">{{ __('support_chat::messages.started_from_label') }}</label>
                                            <span class="text-dark dark-text d-block text-truncate fs-13">{{ $activeThread->started_page_title ?: __('support_chat::messages.unknown_page_label') }}</span>
                                            @if($activeThread->started_page_url)
                                                <a href="{{ $activeThread->started_page_url }}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary mt-2">
                                                    <i class="feather-external-link me-1"></i> {{ __('support_chat::messages.open_origin_page') }}
                                                </a>
                                            @endif
                                        </div>
                                        <div>
                                            <label class="text-muted small text-uppercase fw-bold d-block mb-1">{{ __('support_chat::messages.latest_activity_label') }}</label>
                                            <span class="text-dark dark-text fs-13">{{ optional($activeThread->last_message_at)->diffForHumans() ?? __('support_chat::messages.just_now_label') }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-3 border-bottom">
                                    <form
                                        class="support-chat-sidebar__control"
                                        data-support-chat-assign-form
                                        action="{{ route('admin.support_chat.threads.assign', $activeThread->id) }}"
                                    >
                                        @csrf
                                        <label class="form-label fw-bold small" for="assigned_admin_id">{{ __('support_chat::messages.assign_label') }}</label>
                                        <select class="form-select rounded-3" id="assigned_admin_id" name="assigned_admin_id">
                                            <option value="">{{ __('support_chat::messages.unassigned_label') }}</option>
                                            @foreach($assignees as $assignee)
                                                <option value="{{ $assignee->id }}" @selected((int) ($activeThread->assigned_admin_id ?? 0) === (int) $assignee->id)>
                                                    {{ $assignee->username }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>
                                </div>

                                <div class="p-3 border-bottom">
                                    <label class="form-label fw-bold small">{{ __('support_chat::messages.status_label') }}</label>
                                    <div class="support-chat-status-actions d-grid gap-2" data-support-chat-status-group data-action="{{ route('admin.support_chat.threads.status', $activeThread->id) }}">
                                        @csrf
                                        <div class="btn-group w-100" role="group">
                                            @foreach(['open', 'pending', 'closed'] as $statusOption)
                                                <button
                                                    type="button"
                                                    class="btn btn-sm {{ $activeStatus === $statusOption ? 'btn-primary' : 'btn-light border' }}"
                                                    data-status="{{ $statusOption }}"
                                                >
                                                    {{ __('support_chat::messages.status_' . $statusOption) }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="p-4 text-center text-muted">
                                    <i class="feather-info fs-3 d-block mb-2"></i>
                                    <span class="small">{{ __('support_chat::messages.select_thread_hint') }}</span>
                                </div>
                            @endif
                        </aside>
                    </section>
                @endif
            @else
                {{-- External mode preview card --}}
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                    <div class="bg-soft-primary text-primary rounded-circle d-inline-flex p-4 mx-auto mb-3">
                        <i class="feather-external-link fs-2"></i>
                    </div>
                    <h4 class="fw-bold mb-2">{{ __('support_chat::messages.channel_' . ($settings['channel_mode'] ?? 'whatsapp')) }}</h4>
                    <p class="text-muted max-w-500 mx-auto mb-4">{{ __('support_chat::messages.external_preview_blurb') }}</p>
                    @if($externalUrl)
                        <div>
                            <a href="{{ $externalUrl }}" target="_blank" rel="noopener" class="btn btn-primary rounded-pill px-4">
                                <i class="feather-send me-1"></i> {{ __('support_chat::messages.open_selected_channel') }}
                            </a>
                        </div>
                    @else
                        <p class="text-danger small">{{ __('support_chat::messages.external_channel_missing') }}</p>
                    @endif
                </div>
            @endif
        </div>

        {{-- ================= TAB 2: AI ASSISTANT SETTINGS ================= --}}
        <div class="tab-pane fade {{ $activeTab === 'ai' ? 'show active' : '' }}" id="tab-ai-pane" role="tabpanel" aria-labelledby="tab-ai-btn">
            <form method="POST" action="{{ route('admin.support_chat.settings.update') }}">
                @csrf
                <input type="hidden" name="_tab" value="ai">

                {{-- AI Master Controller Card --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-soft-info text-info p-3 rounded-4">
                                    <i class="feather-cpu fs-3"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold mb-1 text-dark dark-text">{{ __('support_chat::messages.ai_settings_title') }}</h4>
                                    <p class="text-muted small mb-0">{{ __('support_chat::messages.ai_settings_subtitle') }}</p>
                                </div>
                            </div>
                            <div class="form-check form-switch fs-4">
                                <input class="form-check-input" type="checkbox" role="switch" id="ai_enabled" name="ai_enabled" value="1" @checked(!empty($settings['ai_enabled']))>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold" for="ai_mode">{{ __('support_chat::messages.ai_mode_label') }}</label>
                                <select class="form-select rounded-3" id="ai_mode" name="ai_mode">
                                    <option value="auto_reply" @selected(($settings['ai_mode'] ?? 'auto_reply') === 'auto_reply')>{{ __('support_chat::messages.ai_mode_auto_reply') }}</option>
                                    <option value="always_ai" @selected(($settings['ai_mode'] ?? '') === 'always_ai')>{{ __('support_chat::messages.ai_mode_always_ai') }}</option>
                                    <option value="assist_admin" @selected(($settings['ai_mode'] ?? '') === 'assist_admin')>{{ __('support_chat::messages.ai_mode_assist_admin') }}</option>
                                    <option value="off" @selected(($settings['ai_mode'] ?? '') === 'off')>{{ __('support_chat::messages.ai_mode_off') }}</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold" for="ai_provider">{{ __('support_chat::messages.ai_provider_label') }}</label>
                                <select class="form-select rounded-3" id="ai_provider" name="ai_provider">
                                    <option value="pollinations" @selected(($settings['ai_provider'] ?? 'pollinations') === 'pollinations')>{{ __('support_chat::messages.provider_pollinations') }}</option>
                                    <option value="groq" @selected(($settings['ai_provider'] ?? '') === 'groq')>{{ __('support_chat::messages.provider_groq') }}</option>
                                    <option value="gemini" @selected(($settings['ai_provider'] ?? '') === 'gemini')>{{ __('support_chat::messages.provider_gemini') }}</option>
                                    <option value="openai" @selected(($settings['ai_provider'] ?? '') === 'openai')>{{ __('support_chat::messages.provider_openai') }}</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold" for="ai_bot_name">{{ __('support_chat::messages.ai_bot_name_label') }}</label>
                                <input type="text" class="form-control rounded-3" id="ai_bot_name" name="ai_bot_name" value="{{ old('ai_bot_name', $settings['ai_bot_name'] ?? __('support_chat::messages.ai_bot_default_name')) }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 4 AI Providers Configuration Cards Grid --}}
                <div class="row g-4 mb-4">
                    {{-- 1. Pollinations.ai Provider --}}
                    <div class="col-lg-6">
                        <div class="card h-100 border-0 shadow-sm rounded-4 provider-card {{ ($settings['ai_provider'] ?? '') === 'pollinations' ? 'border-primary-subtle' : '' }}">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-soft-primary text-primary p-2 rounded-3"><i class="feather-globe fs-4"></i></div>
                                        <div>
                                            <h5 class="fw-bold mb-0">Pollinations.ai</h5>
                                            <span class="badge bg-soft-success text-success fs-10">Free / Optional Key</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill btn-test-ai" data-provider="pollinations" data-key-input="#pollinations_api_key" data-model-input="#pollinations_model">
                                        <i class="feather-activity me-1"></i> {{ __('support_chat::messages.test_connection_btn') }}
                                    </button>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold" for="pollinations_api_key">{{ __('support_chat::messages.pollinations_api_key_label') }}</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="pollinations_api_key" name="pollinations_api_key" value="{{ old('pollinations_api_key', $settings['pollinations_api_key'] ?? '') }}" placeholder="sk_... or pollinations token">
                                        <button class="btn btn-outline-light border btn-toggle-pw" type="button"><i class="feather-eye"></i></button>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label small fw-bold" for="pollinations_model">{{ __('support_chat::messages.pollinations_model_label') }}</label>
                                    <select class="form-select" id="pollinations_model" name="pollinations_model">
                                        @foreach(\MyAds\Plugins\SupportChat\SupportChatSettings::pollinationsModelOptions() as $groupLabel => $models)
                                            <optgroup label="{{ $groupLabel }}">
                                                @foreach($models as $modelKey => $modelName)
                                                    <option value="{{ $modelKey }}" @selected(($settings['pollinations_model'] ?? 'openai') === $modelKey)>
                                                        {{ $modelName }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="test-result-box mt-3 d-none"></div>
                            </div>
                        </div>
                    </div>

                    {{-- 2. Groq Cloud API Provider --}}
                    <div class="col-lg-6">
                        <div class="card h-100 border-0 shadow-sm rounded-4 provider-card {{ ($settings['ai_provider'] ?? '') === 'groq' ? 'border-primary-subtle' : '' }}">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-soft-warning text-warning p-2 rounded-3"><i class="feather-zap fs-4"></i></div>
                                        <div>
                                            <h5 class="fw-bold mb-0">Groq Cloud API</h5>
                                            <span class="badge bg-soft-warning text-warning fs-10">Ultra-Fast Llama 3.3</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-warning rounded-pill btn-test-ai" data-provider="groq" data-key-input="#groq_api_key" data-model-input="#groq_model">
                                        <i class="feather-activity me-1"></i> {{ __('support_chat::messages.test_connection_btn') }}
                                    </button>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold" for="groq_api_key">{{ __('support_chat::messages.groq_api_key_label') }}</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="groq_api_key" name="groq_api_key" value="{{ old('groq_api_key', $settings['groq_api_key'] ?? '') }}" placeholder="gsk_...">
                                        <button class="btn btn-outline-light border btn-toggle-pw" type="button"><i class="feather-eye"></i></button>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label small fw-bold" for="groq_model">{{ __('support_chat::messages.groq_model_label') }}</label>
                                    <select class="form-select" id="groq_model" name="groq_model">
                                        <option value="llama-3.3-70b-versatile" @selected(($settings['groq_model'] ?? 'llama-3.3-70b-versatile') === 'llama-3.3-70b-versatile')>llama-3.3-70b-versatile (Recommended)</option>
                                        <option value="llama-3.1-8b-instant" @selected(($settings['groq_model'] ?? '') === 'llama-3.1-8b-instant')>llama-3.1-8b-instant (Fastest)</option>
                                        <option value="mixtral-8x7b-32768" @selected(($settings['groq_model'] ?? '') === 'mixtral-8x7b-32768')>mixtral-8x7b-32768</option>
                                    </select>
                                </div>
                                <div class="test-result-box mt-3 d-none"></div>
                            </div>
                        </div>
                    </div>

                    {{-- 3. Google Gemini API Provider --}}
                    <div class="col-lg-6">
                        <div class="card h-100 border-0 shadow-sm rounded-4 provider-card {{ ($settings['ai_provider'] ?? '') === 'gemini' ? 'border-primary-subtle' : '' }}">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-soft-info text-info p-2 rounded-3"><i class="feather-triangle fs-4"></i></div>
                                        <div>
                                            <h5 class="fw-bold mb-0">Google Gemini API</h5>
                                            <span class="badge bg-soft-info text-info fs-10">Gemini 2.0 / 1.5 Flash</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-info rounded-pill btn-test-ai" data-provider="gemini" data-key-input="#gemini_api_key" data-model-input="#gemini_model">
                                        <i class="feather-activity me-1"></i> {{ __('support_chat::messages.test_connection_btn') }}
                                    </button>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold" for="gemini_api_key">{{ __('support_chat::messages.gemini_api_key_label') }}</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="gemini_api_key" name="gemini_api_key" value="{{ old('gemini_api_key', $settings['gemini_api_key'] ?? '') }}" placeholder="AIzaSy...">
                                        <button class="btn btn-outline-light border btn-toggle-pw" type="button"><i class="feather-eye"></i></button>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label small fw-bold" for="gemini_model">{{ __('support_chat::messages.gemini_model_label') }}</label>
                                    <select class="form-select" id="gemini_model" name="gemini_model">
                                        <option value="gemini-1.5-flash" @selected(($settings['gemini_model'] ?? 'gemini-1.5-flash') === 'gemini-1.5-flash')>gemini-1.5-flash (Standard)</option>
                                        <option value="gemini-2.0-flash" @selected(($settings['gemini_model'] ?? '') === 'gemini-2.0-flash')>gemini-2.0-flash (Next-Gen)</option>
                                        <option value="gemini-1.5-pro" @selected(($settings['gemini_model'] ?? '') === 'gemini-1.5-pro')>gemini-1.5-pro (High Intelligence)</option>
                                    </select>
                                </div>
                                <div class="test-result-box mt-3 d-none"></div>
                            </div>
                        </div>
                    </div>

                    {{-- 4. OpenAI API Provider --}}
                    <div class="col-lg-6">
                        <div class="card h-100 border-0 shadow-sm rounded-4 provider-card {{ ($settings['ai_provider'] ?? '') === 'openai' ? 'border-primary-subtle' : '' }}">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-soft-success text-success p-2 rounded-3"><i class="feather-layers fs-4"></i></div>
                                        <div>
                                            <h5 class="fw-bold mb-0">OpenAI API</h5>
                                            <span class="badge bg-soft-success text-success fs-10">GPT-4o / GPT-4o-mini</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill btn-test-ai" data-provider="openai" data-key-input="#openai_api_key" data-model-input="#openai_model">
                                        <i class="feather-activity me-1"></i> {{ __('support_chat::messages.test_connection_btn') }}
                                    </button>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold" for="openai_api_key">{{ __('support_chat::messages.openai_api_key_label') }}</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="openai_api_key" name="openai_api_key" value="{{ old('openai_api_key', $settings['openai_api_key'] ?? '') }}" placeholder="sk-proj-...">
                                        <button class="btn btn-outline-light border btn-toggle-pw" type="button"><i class="feather-eye"></i></button>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label small fw-bold" for="openai_model">{{ __('support_chat::messages.openai_model_label') }}</label>
                                    <select class="form-select" id="openai_model" name="openai_model">
                                        <option value="gpt-4o-mini" @selected(($settings['openai_model'] ?? 'gpt-4o-mini') === 'gpt-4o-mini')>gpt-4o-mini (Fast & Cost Effective)</option>
                                        <option value="gpt-4o" @selected(($settings['openai_model'] ?? '') === 'gpt-4o')>gpt-4o (Flagship Model)</option>
                                        <option value="gpt-3.5-turbo" @selected(($settings['openai_model'] ?? '') === 'gpt-3.5-turbo')>gpt-3.5-turbo</option>
                                    </select>
                                </div>
                                <div class="test-result-box mt-3 d-none"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Persona & System Prompt Card --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h5 class="fw-bold mb-1">{{ __('support_chat::messages.ai_system_prompt_label') }}</h5>
                                <p class="text-muted small mb-0">{{ __('support_chat::messages.ai_system_prompt_help') }}</p>
                            </div>
                        </div>
                        <textarea
                            class="form-control rounded-3 p-3 mb-3"
                            id="ai_system_prompt"
                            name="ai_system_prompt"
                            rows="5"
                            placeholder="أنت المساعد الذكي لموقع MYADS، تقوم بالرد على استفسارات الزوار بلباقة ومساعدتهم في نشر الإعلانات واستخدام المنتدى والمتجر..."
                        >{{ old('ai_system_prompt', $settings['ai_system_prompt'] ?? '') }}</textarea>
                    </div>
                </div>

                {{-- Hidden channel settings preserved when saving from AI tab --}}
                <input type="hidden" name="channel_mode" value="{{ $settings['channel_mode'] ?? 'local' }}">
                <input type="hidden" name="widget_enabled" value="{{ !empty($settings['widget_enabled']) ? '1' : '0' }}">
                <input type="hidden" name="team_name" value="{{ $settings['team_name'] ?? 'MYADS Support' }}">
                <input type="hidden" name="welcome_message" value="{{ $settings['welcome_message'] ?? '' }}">
                <input type="hidden" name="whatsapp_number" value="{{ $settings['whatsapp_number'] ?? '' }}">
                <input type="hidden" name="whatsapp_prefill" value="{{ $settings['whatsapp_prefill'] ?? '' }}">
                <input type="hidden" name="messenger_url" value="{{ $settings['messenger_url'] ?? '' }}">
                <input type="hidden" name="visibility_rules" value="{{ $settings['visibility_rules'] ?? '' }}">

                <div class="d-flex justify-content-end mb-4">
                    <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm">
                        <i class="feather-save me-1"></i> {{ __('support_chat::messages.save_settings_label') }}
                    </button>
                </div>
            </form>
        </div>

        {{-- ================= TAB 3: CHANNELS & WIDGET SETTINGS ================= --}}
        <div class="tab-pane fade {{ $activeTab === 'channels' ? 'show active' : '' }}" id="tab-channels-pane" role="tabpanel" aria-labelledby="tab-channels-btn">
            <form method="POST" action="{{ route('admin.support_chat.settings.update') }}">
                @csrf
                <input type="hidden" name="_tab" value="channels">

                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-3">
                            <div class="bg-soft-primary text-primary p-3 rounded-4">
                                <i class="feather-sliders fs-3"></i>
                            </div>
                            <div>
                                <h4 class="fw-bold mb-1 text-dark dark-text">{{ __('support_chat::messages.settings_title') }}</h4>
                                <p class="text-muted small mb-0">{{ __('support_chat::messages.settings_subtitle') }}</p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="channel_mode">{{ __('support_chat::messages.channel_mode_label') }}</label>
                                <select class="form-select rounded-3" id="channel_mode" name="channel_mode">
                                    <option value="local" @selected(($settings['channel_mode'] ?? 'local') === 'local')>{{ __('support_chat::messages.channel_local') }}</option>
                                    <option value="whatsapp" @selected(($settings['channel_mode'] ?? '') === 'whatsapp')>{{ __('support_chat::messages.channel_whatsapp') }}</option>
                                    <option value="messenger" @selected(($settings['channel_mode'] ?? '') === 'messenger')>{{ __('support_chat::messages.channel_messenger') }}</option>
                                </select>
                            </div>

                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check form-switch fs-5 pt-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="widget_enabled" name="widget_enabled" value="1" @checked(!empty($settings['widget_enabled']))>
                                    <label class="form-check-label ms-2 fs-14 fw-bold" for="widget_enabled">{{ __('support_chat::messages.show_widget_label') }}</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="team_name">{{ __('support_chat::messages.team_name_label') }}</label>
                                <input type="text" class="form-control rounded-3" id="team_name" name="team_name" value="{{ old('team_name', $settings['team_name'] ?? '') }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="welcome_message">{{ __('support_chat::messages.welcome_message_label') }}</label>
                                <textarea class="form-control rounded-3" id="welcome_message" name="welcome_message" rows="2">{{ old('welcome_message', $settings['welcome_message'] ?? '') }}</textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="whatsapp_number">{{ __('support_chat::messages.whatsapp_number_label') }}</label>
                                <input type="text" class="form-control rounded-3" id="whatsapp_number" name="whatsapp_number" value="{{ old('whatsapp_number', $settings['whatsapp_number'] ?? '') }}" placeholder="216...">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="whatsapp_prefill">{{ __('support_chat::messages.whatsapp_prefill_label') }}</label>
                                <input type="text" class="form-control rounded-3" id="whatsapp_prefill" name="whatsapp_prefill" value="{{ old('whatsapp_prefill', $settings['whatsapp_prefill'] ?? '') }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="messenger_url">{{ __('support_chat::messages.messenger_url_label') }}</label>
                                <input type="url" class="form-control rounded-3" id="messenger_url" name="messenger_url" value="{{ old('messenger_url', $settings['messenger_url'] ?? '') }}" placeholder="https://m.me/yourpage">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="visibility_rules">{{ __('support_chat::messages.visibility_rules_label') }}</label>
                                <textarea class="form-control rounded-3" id="visibility_rules" name="visibility_rules" rows="3" placeholder="admin*&#10;login*">{{ old('visibility_rules', $settings['visibility_rules'] ?? '') }}</textarea>
                                <small class="text-muted">{{ __('support_chat::messages.visibility_rules_help') }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Hidden AI settings preserved when saving from Channels tab --}}
                <input type="hidden" name="ai_enabled" value="{{ !empty($settings['ai_enabled']) ? '1' : '0' }}">
                <input type="hidden" name="ai_mode" value="{{ $settings['ai_mode'] ?? 'auto_reply' }}">
                <input type="hidden" name="ai_provider" value="{{ $settings['ai_provider'] ?? 'pollinations' }}">
                <input type="hidden" name="ai_bot_name" value="{{ $settings['ai_bot_name'] ?? 'مساعد MYADS الذكي' }}">
                <input type="hidden" name="pollinations_api_key" value="{{ $settings['pollinations_api_key'] ?? '' }}">
                <input type="hidden" name="pollinations_model" value="{{ $settings['pollinations_model'] ?? 'openai' }}">
                <input type="hidden" name="groq_api_key" value="{{ $settings['groq_api_key'] ?? '' }}">
                <input type="hidden" name="groq_model" value="{{ $settings['groq_model'] ?? 'llama-3.3-70b-versatile' }}">
                <input type="hidden" name="gemini_api_key" value="{{ $settings['gemini_api_key'] ?? '' }}">
                <input type="hidden" name="gemini_model" value="{{ $settings['gemini_model'] ?? 'gemini-1.5-flash' }}">
                <input type="hidden" name="openai_api_key" value="{{ $settings['openai_api_key'] ?? '' }}">
                <input type="hidden" name="openai_model" value="{{ $settings['openai_model'] ?? 'gpt-4o-mini' }}">
                <input type="hidden" name="ai_system_prompt" value="{{ $settings['ai_system_prompt'] ?? '' }}">

                <div class="d-flex justify-content-end mb-4">
                    <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm">
                        <i class="feather-save me-1"></i> {{ __('support_chat::messages.save_settings_label') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.SUPPORT_CHAT_CONFIG = {
        testAiUrl: "{{ route('admin.support_chat.ai.test') }}",
        csrfToken: "{{ csrf_token() }}"
    };
</script>
<script src="{{ route('support_chat.asset', ['path' => 'admin-support-chat.js']) }}"></script>
@endpush
