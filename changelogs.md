# Support Chat - Changelog

## v1.2.0
* **Feature**: Multi-Provider AI Support Engine supporting Pollinations.ai (keyless or tokenized), Groq Cloud API (Llama 3.3), Google Gemini API (Gemini 2.0 / 1.5), and OpenAI API (GPT-4o / GPT-4o-mini).
* **Feature**: Instant 24/7 AI Auto-Reply to public visitors and community members with rich site/page context.
* **Feature**: Admin AI Co-Pilot with one-click "✨ Suggest Reply" featuring synchronized animated loading spinners (`<span class="spinner-border">`), textarea loading overlay, reading latest conversation context, and direct drafting into the message compose box with auto-focus & glowing pulse feedback.
* **Feature**: Live AI Provider Connection Test tool with instant validation and sample reply generation.
* **UI Redesign**: Full overhaul of `/admin/support-chat` adhering to `.superdesign` guidelines (Hero banner, KPI metric cards, segmented tabs, 3-column live workspace, dark mode & RTL support).
* **UI/UX & Bugfix**: Fixed member & guest avatar scaling and dimensions by enforcing strict container boundaries (`max-width`, `min-width`, `object-fit: cover`) and integrating automatic inline stylesheet fallbacks into `@push('styles')`.
* **UI/UX**: Integrated uniform Hexagonal Avatars (`support-chat-avatar-hex-wrap` with `clip-path`) across all live inbox thread cards, message bubbles, active headers, and user detail sidebars.
* **UI/UX**: Organized Pollinations.ai model selection into a comprehensive categorized `<select>` dropdown based on the official `https://gen.pollinations.ai/models` specification (OpenAI, Claude, Gemini, DeepSeek, Llama, Mistral, Qwen, Perplexity, Grok).
* **Fix & Reliability**: Fixed Google Gemini API `HTTP 400 Bad Request` by automatically merging consecutive multi-turn messages of the same role into valid alternating `user`/`model` conversational sequences.
* **Fix & Reliability**: Fixed the "All" (`filter=all`) filter in admin inbox to correctly display all conversation threads (open, pending, and closed).
* **Fix & Reliability**: Fixed dynamic model propagation in keyless Pollinations GET requests instead of hardcoding default models.
* **Fix & Reliability**: Eliminated duplicate message dispatch and duplicate AI auto-replies by removing redundant widget injection mechanisms in `boot.php`, enforcing strict single-instance bootstrap in `support-chat.js`, and adding 4-second message & AI auto-reply deduplication guards in `SupportChatService` and admin polling JS.
* **Fix & Reliability**: Resolved `MethodNotAllowedHttpException` on admin reply form by explicitly defining `method="POST"`, supporting dual `GET`/`POST` route fallback, and enhancing dynamic CSRF token resolution.
* **Fix & Reliability**: Added safe `File::exists()` guards on widget asset loading to prevent runtime exceptions if assets are unreadable.
* **Localization**: Synchronized and completed all AI & KPI translation dictionaries across all 9 supported languages (`ar`, `en`, `fr`, `de`, `es`, `fa`, `it`, `pt`, `tr`).
* **Testing & Core**: Added `support-chat` to the automated testing suite and plugin discovery in `PluginServiceProvider`.

## v1.0.1
* **Fix**: Resolved CSS and JavaScript asset loading issues in the admin panel by migrating plugin assets to a non-restricted directory.
* **Fix**: Eliminated duplicate message submissions by implementing frontend button locking and backend deduplication logic.
* **Fix**: Corrected admin layout rendering following the platform's migration to independent admin themes.
* **Improvement**: Added unread message counters and real-time status updates in the chat interface.
* **Initial Release**: Basic support chat functionality with WhatsApp and Messenger integration.
* **Feature**: Admin inbox for managing local support conversations.
* **UI**: Floating support widget for public users.
