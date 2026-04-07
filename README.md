# Support Chat Plugin for MYADS

> **Version:** 1.0.1  
> **Author:** MrGhozzi  
> **Minimum MYADS Version:** v4.2.4

## Overview
The **Support Chat** plugin is a powerful extension for MYADS that integrates a multi-channel support system directly into your platform. It allows visitors and members to reach out to administrators through a centralized floating widget, supporting local chat, WhatsApp, and Facebook Messenger.

## Key Features
- **Floating Multi-Channel Widget**: A sleek, customizable support button that appears on all public pages.
- **Local Chat System**: Real-time messaging with browser sessions, allowing guests and members to chat directly without leaving the site.
- **Social Integration**: 
    - **WhatsApp**: Direct link to your WhatsApp business profile.
    - **Messenger**: Integration with Facebook Messenger for social-first support.
- **Admin Inbox**: A dedicated management interface at `/admin/support-chat` to handle all incoming inquiries.
- **Real-time Notifications**: Unread message counters dynamically appear in the admin sidebar via `@feather-message-circle` icon.
- **Security & Performance**:
    - **Throttling**: Built-in rate limiting for message submissions to prevent spam.
    - **Clean Assets**: Dedicated asset loading via `/support-chat/assets/` to bypass strict admin theme restrictions.
    - **Persistence**: Chat threads are persisted across sessions for guests.

## Administrative Interface
Administrators can access the Support Chat dashboard through the main sidebar. Key actions include:
- **Managing Threads**: Mark as open, closed, or pending.
- **Direct Replies**: Send real-time responses to users.
- **Settings**: Configure WhatsApp/Messenger details and widget appearance.

## Technical Architecture
- **Namespace**: `MyAds\Plugins\SupportChat`
- **Hook Integration**:
    - `theme_master_head_end`: Injects CSS and configuration.
    - `theme_master_before_body_close`: Renders the widget and initializes JS.
    - `admin_sidebar_menu`: Integrates with the Duralux admin panel.
- **Database**: Uses `support_chat_threads` and `support_chat_messages` tables.

## Installation
1. Upload the plugin ZIP bundle through the **Admin > Plugins** interface.
2. Click **Activate** to initialize the database tables and register routes.
3. Access the **Support Chat** link in the admin sidebar to start responding to users.

---
© 2026 MYADS - Advanced Ad Exchange & Social Platform.
