# Whale AI

Whale AI is a local-first AI workbench built with Laravel: a streaming chat
assistant, a media studio, and a file workspace with its own terminal — answered
by hosted providers, local model files, or any OpenAI-compatible endpoint you
already run.

## Highlights

**Chat** (`/ai/chat`)

- Server-sent-event streaming replies with stop, regenerate, edit-and-resend,
  copy, read-aloud, reactions, and hover timestamps.
- Twelve assistant modes — Chat, Ask, Plan, Code, Debug, Agent, Orchestrate,
  Research, Deep search, Study, Security study, Art — with tool policies
  enforced by the tool list itself; read-only modes cannot write files.
- Response depth, thinking effort, response length, plus web-search and
  deep-search toggles.
- Collapsible reasoning blocks, markdown with syntax-highlighted code blocks
  (copy / open in sandbox), image and video attachments, slash commands,
  @-mention sub-agents, and prompt enhancement.
- Projects, searchable conversation history, public share links, and export to
  txt, Markdown, HTML, JSON or PDF.
- Light and dark themes, reading preferences (text size, transcript density,
  reduced motion), and a resizable sidebar with a mobile drawer.

**Models & providers**

- Hosted providers through env keys: OpenAI, Anthropic, Gemini, Groq, DeepSeek,
  Mistral, xAI, OpenRouter, Cohere, Azure OpenAI, Amazon Bedrock, ElevenLabs,
  Ollama, and any OpenAI-compatible endpoint (full list in `config/ai.php`).
- Any number of extra endpoints declared in one JSON variable
  (`WHALE_CUSTOM_PROVIDERS`), with model discovery, import and re-sync from the
  settings view.
- Drop-in `.gguf` and `.safetensors` detection: headers are profiled for
  metadata and capabilities, but weights are never executed by the app —
  generation is delegated to llama.cpp, Ollama, or a Python image runtime, each
  health-probed before it is reported ready.
- Model picker with type filters (all / reasoning / fast / image / media).

**Media studio** (`/ai/chat/studio`)

- Image generation and editing from a source picture with 21 style presets,
  SVG/vector export, OCR text extraction, text-to-speech audio, and a video
  storyboard that renders generated frames into an HTML clip.
- An in-chat draw/scratch board that feeds generation, plus an OpenAI-compatible
  image provider for endpoints such as Pollinations or LM Studio.

**Workspace IDE** (`/ai/chat/workspace`)

- File tree, tabbed editor with dirty tracking, save, upload, mkdir, move,
  delete, search-in-files, preview, split layouts, command palette, and export.
- A sandboxed terminal with an allowlisted command set, and a package manager
  (composer / npm / pip) restricted to non-scripting subcommands.
- @-file mentions in the composer and code-block sandbox execution.

**Speech & capture**

- Live microphone transcription sent as short clips, so words appear while you
  are still talking.
- Two engines behind one interface: Whistle (free, on-device, offline) and the
  AI SDK, with FFmpeg normalising browser WebM audio to 16 kHz mono WAV.
- Camera capture with vision-model scene description.

**Accounts, staff & billing**

- Optional sign-in gate (`WHALE_REQUIRE_AUTH`), email/password auth, and Google
  or GitHub OAuth where the first callback registers the account.
- Roles (user / staff / admin) kept separate from plans; `WHALE_ADMIN_EMAILS`
  allowlists the `/admin` dashboard, and staff can open `/staff/billing`.
- Plans Free / Pro ($19) / Premium ($49) gated by a single `plan:` middleware,
  with a public pricing page, Stripe checkout, customer portal, and
  signature-verified webhooks.

**Site**

- Public landing page with auth modal, `/pricing`, and docs at `/docs`
  (introduction, authentication, chat completions, models, errors) whose slugs
  are validated against `config/docs.php`.

## Routes

| Path | Page |
| --- | --- |
| `/` | Landing page |
| `/ai/chat` | Chat app |
| `/ai/chat/studio` | Media studio |
| `/ai/chat/workspace` | Workspace IDE |
| `/docs` | Public documentation |
| `/pricing`, `/billing` | Pricing and billing |
| `/login` | Sign in (shown when the auth gate is on) |
| `/admin`, `/staff/billing` | Admin dashboard and staff area |

## Requirements

- PHP 8.3+ (developed and run on PHP 8.5) and Composer
- Node.js 20+ (v25 tested)
- SQLite (default) — MySQL and PostgreSQL also work
- Optional: FFmpeg (browser microphone clips), the Whistle binary (offline
  transcription), llama.cpp / Ollama / diffusers runtimes (local models),
  Stripe keys (billing), Google and GitHub OAuth apps (social sign-in)

## Installation

```bash
composer run setup
```

This runs `composer install`, copies `.env` when it is missing, generates the
app key, migrates the database, installs npm packages, and builds the assets.
Then start it:

```bash
php artisan serve      # or point Laravel Herd / Valet at the project
```

## Configuration

Everything is env-driven; `.env.example` carries the annotated list. The main
groups:

| Area | Variables |
| --- | --- |
| AI providers | `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`, `GROQ_API_KEY`, `OLLAMA_URL`, … (see `config/ai.php`) |
| Custom endpoints | `WHALE_CUSTOM_PROVIDERS` (JSON array of `{name, url, key, …}`), `WHALE_CUSTOM_PROVIDERS_MODELS_PATH` |
| Local models | `WHALE_MODELS_PATH`, `LOCAL_LLM_URL`, `LOCAL_IMAGE_URL`, `WHALE_DETECT_CAPABILITIES`, `WHALE_MODEL_CACHE_TTL` |
| Speech | `WHALE_STT_ENGINE` (`auto` / `whistle` / `sdk`), `WHISTLE_BINARY`, `WHALE_SPEECH_LANGUAGE` |
| Auth | `WHALE_REQUIRE_AUTH`, `GOOGLE_CLIENT_ID`/`SECRET`, `GITHUB_CLIENT_ID`/`SECRET` |
| Access | `WHALE_ADMIN_EMAILS` (comma-separated; empty means nobody) |
| Billing | `BILLING_STRIPE_SECRET`, `BILLING_STRIPE_WEBHOOK_SECRET`, `BILLING_STRIPE_PRICE_PRO`, `BILLING_STRIPE_PRICE_PREMIUM` |
| MCP servers | `MCP_LINEAR_URL`, `MCP_LINEAR_TOKEN`, `MCP_FILESYSTEM_COMMAND` |

Run `php artisan config:clear` after editing `.env` while a config cache is
active.

## Development

```bash
composer run dev       # php artisan serve + queue listener + Vite, in one command
npm run build          # production assets
npm run dev            # assets only
```

## Testing

```bash
php artisan test --compact              # Pest feature suite (273 tests, 1,131 assertions)
vendor/bin/pint --dirty --format agent  # format changed PHP files
```

An optional end-to-end smoke check drives a real headless Chrome over the
DevTools protocol:

```bash
php artisan serve --port=8000
chrome --headless=new --remote-debugging-port=9223 --user-data-dir=/tmp/whale-cdp
APP_URL=http://127.0.0.1:8000/ai/chat node browser-check.mjs
```

## Project structure

```
app/Ai/            agents, tools, model catalog, local model parsers, speech
app/Billing/       Plan and Role enums, Stripe gateway
app/Http/          chat, media, workspace, conversation, auth, admin controllers
app/Mcp/           MCP client tools and the workspace MCP server
app/Workspace/     workspace filesystem, terminal, package manager
config/            ai.php, whale.php, billing.php, docs.php, mcp.php, admin.php
resources/views/   Blade + Alpine.js UI (chat, studio, workspace, docs, billing)
resources/css/     Tailwind CSS 4 theme, semantic tokens, prose and code styles
routes/            web.php, chat.php (mounted under /ai), admin.php, api.php
tests/Feature/     Pest suite (273 tests)
```

## Working with AI agents

`AGENTS.md` (mirrored as `CLAUDE.md`) carries the Laravel Boost guidelines for
this repository, and `.agents/skills/` holds reusable domain skills — Laravel,
Tailwind, testing, publishing, and more — that agents activate while working
here. `.ai/rules/` path-scoped rules apply when that directory exists.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for all notable changes.

## License

MIT — see the `license` field in [composer.json](composer.json).
