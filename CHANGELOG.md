# Changelog

All notable changes to **Whale AI** are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
the project intends to follow [Semantic Versioning](https://semver.org/).

The repository currently has a single squashed "Initial commit", so everything
present in the working tree is recorded under `[Unreleased]`. A `x.y.z` heading
is added here whenever a version is tagged.

## [Unreleased]

### Added — Chat (`/ai/chat`)

- Server-sent-event streaming replies with stop, regenerate, edit-and-resend,
  copy, read-aloud, thumbs up/down reactions and hover timestamps.
- Twelve assistant modes — Chat, Ask, Plan, Code, Debug, Agent, Orchestrate,
  Research, Deep search, Study, Security study, Art — each with an explicit tool
  policy; read-only modes (Ask, Plan, Debug) are refused write tools by the tool
  list itself rather than by prompting.
- Response depth (Super fast / Fast / Deep thinking), thinking effort
  (low → extra-high), response length (auto/short/medium/long), and toggles for
  web search and deep search.
- Collapsible reasoning blocks, markdown rendering with syntax-highlighted code
  blocks (Copy / Open in sandbox), image and video attachments with previews.
- Slash commands, @-mention sub-agents (coding, research, deep search, study,
  ethical hacking, art), a selection toolbar, and prompt enhancement.
- Composer niceties: rotating starter placeholders, character counter, live
  microphone capture, pending-attachment tray, per-file validation errors.
- Reading preferences (text size, transcript density, reduced motion), light and
  dark themes, a resizable/collapsible history sidebar with mobile drawer.

### Added — Conversations

- Persistent conversations and projects: create, rename, pin, archive, delete,
  free-text search, and automatic Today / Yesterday / Previous 7 days / Older
  grouping in the sidebar.
- Public share links with a read-only view, and export as txt, Markdown, HTML,
  JSON or PDF.

### Added — Models & providers

- Laravel AI SDK integration with hosted providers configured through env keys
  (OpenAI, Anthropic, Gemini, Groq, DeepSeek, Mistral, xAI, OpenRouter, Cohere,
  Azure OpenAI, Amazon Bedrock, ElevenLabs, Jina, Voyage, Ollama, and any
  OpenAI-compatible endpoint).
- Custom OpenAI-compatible endpoints declared as one JSON env var
  (`WHALE_CUSTOM_PROVIDERS`), with model discovery, import, and re-sync from the
  settings view.
- Local `.gguf` and `.safetensors` file detection with header profiling,
  capability inference and a size/mtime metadata cache. The app never executes
  weights: generation is delegated to llama.cpp, Ollama, or a Python image
  runtime, each probed for health before being reported ready.
- Model picker with type filters (all / reasoning / fast / image / media) and
  per-provider configuration state.

### Added — Media studio (`/ai/chat/studio`)

- Image generation and image editing from a source picture, with 21 style
  presets, SVG/vector export, OCR text extraction, text-to-speech audio, and a
  video storyboard that renders generated frames into an HTML clip.
- In-chat draw/scratch board that feeds generation, plus an OpenAI-compatible
  image provider for endpoints such as Pollinations or LM Studio.

### Added — Workspace IDE (`/ai/chat/workspace`)

- File tree with directories, multi-tab editor with dirty tracking, save,
  upload, mkdir, move, delete, in-file search and search-in-files, preview,
  split layouts, command palette, and workspace export.
- Sandboxed terminal with an allowlisted command set and a package manager
  (composer / npm / pip) restricted to non-scripting subcommands.
- @-file mentions in the composer and code-block sandbox execution.

### Added — Speech & capture

- Live microphone transcription sent as short clips so words appear while the
  user is still talking.
- Two transcription engines behind one interface: Whistle (free, on-device,
  offline) and the AI SDK, with FFmpeg normalisation of browser WebM audio.
- Camera capture with vision-model scene description.

### Added — Agents, tools & MCP

- Workspace file tools (read, write, list, search, delete) and web research
  tools selected per mode.
- External MCP servers over web and local transports (e.g. Linear, filesystem)
  and an internal MCP server exposing workspace tools.

### Added — Accounts, staff & billing

- Optional sign-in gate (`WHALE_REQUIRE_AUTH`), email/password auth, and Google
  and GitHub OAuth where the first callback registers the account.
- Roles (user / staff / admin) kept separate from plans; an email allowlist
  (`WHALE_ADMIN_EMAILS`) gates the `/admin` dashboard, and staff can reach
  `/staff/billing`.
- Plans Free / Pro ($19) / Premium ($49) with feature gates enforced by a
  single `plan:` middleware, a public pricing page, Stripe checkout, customer
  portal, and signature-verified webhooks.

### Added — Site

- Public landing page with auth modal, `/pricing`, and public docs at `/docs`
  (introduction, authentication, chat completions, models, errors) whose slugs
  are validated against `config/docs.php` so an unknown page 404s.

### Added — Developer experience

- `composer run setup` (install, env, key, migrate, assets) and
  `composer run dev` (server + queue + Vite), a Pest suite of 273 tests /
  1,131 assertions, Pint formatting, Laravel Boost rules in `AGENTS.md`, and a
  headless-Chrome `browser-check.mjs` smoke test.

### Changed

- Chat message design: the sender's bubble is now accent-tinted with a hairline
  ring, assistant replies carry the brand avatar, and the message action rail
  with timestamps stays visible on touch widths instead of being hover-only
  (2026-10-08).
- `.gitignore` reorganised into sections; model weights (`*.gguf`,
  `*.safetensors`), `.env.*` variants, and local tooling state (`.kilo/`,
  `.freebuff/`) are now ignored (2026-10-08).

### Fixed

- The browser tab always read "Chat · Whale AI" because the layout read an
  unset `$title` variable instead of yielding the `title` section; every page
  now shows its own title (2026-10-08).

### Security

- The public shared-conversation title is escaped before it reaches `<title>`,
  closing a stored cross-site-scripting path on share links (2026-10-08).
