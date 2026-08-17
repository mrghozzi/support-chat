# Support Chat Plugin for MYADS

> **Version:** 1.2.0  
> **Author:** MrGhozzi  
> **Minimum MYADS Version:** v4.2.4

## Overview
The **Support Chat** plugin is an enterprise-grade extension for MYADS integrating a multi-channel, AI-augmented customer support infrastructure directly into your platform. It delivers real-time visitor and member messaging through a sleek floating widget, powered by a multi-provider AI auto-reply and admin co-pilot engine, paired with a modern `.superdesign` 3-column administration workspace.

---

## Key Features

### 🤖 Multi-Provider AI Support Engine
- **Pollinations.ai Integration**: Keyless or tokenized access with official model selection (OpenAI, Claude, Gemini, DeepSeek, Llama, Mistral, Qwen, Perplexity, Grok).
- **Groq Cloud API**: Ultra-fast inference with `llama-3.3-70b-versatile` and `llama-3.1-8b-instant`.
- **Google Gemini API**: Native support for `gemini-2.0-flash` and `gemini-1.5-flash`.
- **OpenAI API**: Official integration with `gpt-4o`, `gpt-4o-mini`, and custom models.
- **24/7 AI Auto-Reply**: Contextual auto-replies utilizing page titles, origin URLs, user identity, and custom site instructions.
- **Admin AI Co-Pilot**: One-click `✨ Suggest Reply` within live conversations with custom instruction steering and 1-click text application.
- **Connection Test Suite**: Real-time diagnostic tool to validate API keys and test response latency directly from settings.

### 💬 Live Multi-Channel Workspace & Widget
- **Floating Public Widget**: Customizable floating support button with guest identity capture, member recognition, unread counters, and typing states.
- **Hexagonal Avatars**: MYADS platform standard hexagonal avatar styling (`clip-path`) with status badges and glowing gradient borders.
- **3-Column Live Inbox**:
  - **Thread List**: Filterable conversations (Open, Pending, Closed) with live search and unread badges.
  - **Conversation Stream**: Real-time messaging with role-based message bubbles (Visitor, Admin, AI Assistant) and keyboard shortcuts (`Ctrl+Enter`).
  - **Visitor Profile Sidebar**: Complete session metadata, originating page reference, assignment selector, and instant status switcher.
- **External Channels**: Optional 1-click fallback to WhatsApp and Facebook Messenger.

### 🛡️ Reliability & Deduplication Architecture
- **Single-Instance Bootstrapping**: Guarded hook injection preventing duplicate script evaluations or duplicate widget nodes.
- **Backend Deduplication**: Request throttle and 4-second idempotency checks preventing duplicate message submissions and redundant AI replies.
- **Asset Gateway**: Secure plugin asset streaming via `/support-chat/assets/` avoiding filesystem and permission conflicts.

---

## Technical Architecture
- **Namespace**: `MyAds\Plugins\SupportChat`
- **Core Services**:
  - `SupportChatService`: Core messaging, thread lifecycle, and deduplication logic.
  - `SupportChatAiService`: Multi-provider AI orchestration, prompt compilation, and fallback recovery.
  - `SupportChatSchema`: Database schema provisioning and table health inspection.
  - `SupportChatSettings`: Configuration management and model catalog.
- **Database Tables**:
  - `support_chat_threads`: Manages user/guest conversation threads, session metadata, and assignment.
  - `support_chat_messages`: Stores message history with `sender_type` (`member`, `guest`, `admin`, `ai`).

---

## Installation & Setup
1. Upload and activate the plugin via **Admin > Plugins**.
2. Navigate to **Admin > Support Chat** in the sidebar.
3. In the **AI Settings & Models** tab, choose your preferred provider (e.g. Pollinations, Groq, Gemini, OpenAI), select a model, and click **Test Connection**.
4. Enable **AI Auto-Reply** to provide 24/7 instant support to your users.

---
© 2026 MYADS - Advanced Ad Exchange & Social Platform.
