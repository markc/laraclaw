# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

LaRaClaw is a self-hosted AI agent platform — an OpenClaw reimplementation in Laravel.
Three interaction channels: Web Chat (Inertia/React), TUI (Laravel Prompts), Email.

## Tech Stack

- PHP 8.4+, Laravel 12, Inertia 2, React 18, TypeScript, Tailwind CSS 4
- Laravel/AI SDK (`laravel/ai`) for multi-provider LLM abstraction
- Laravel Reverb for WebSocket (real-time streaming)
- SQLite (dev), PostgreSQL + pgvector (production)
- Vite 7, Laravel Reverb WebSocket hub for real-time streaming
- Bun (package manager), Pest (testing framework)

## Commands

```bash
composer dev                    # Start reverb + queue + logs + Vite (4 processes; FrankenPHP serves HTTP)
composer test                   # Run Pest test suite (clears config cache first)
vendor/bin/pest --filter=ChatTest   # Run a single test class
vendor/bin/pest --filter=test_name  # Run a single test method
bun run build                   # TypeScript check + Vite production build
bun run dev                     # Vite dev server only (use `composer dev` for full stack)
bun install                     # Install frontend dependencies
php artisan migrate             # Run migrations
php artisan migrate:fresh --seed # Reset database with default agent + user
vendor/bin/pint                 # PHP code style fixer (Laravel Pint)
```

## Architecture

### Request Flow (Web Chat — Reverb WebSocket)

1. `POST /chat/send` → `ChatController::send()` validates input, builds `IncomingMessage` DTO, dispatches `ProcessChatMessage` job, returns `{session_key}` JSON immediately
2. `ProcessChatMessage` (queued job) calls `AgentRuntime::streamAndBroadcast()`
3. `streamAndBroadcast()` resolves session, saves user message, builds agent, calls `$agent->broadcastNow()` which iterates stream events and broadcasts each via Reverb
4. SDK broadcasts `stream_start`, `text_delta` (many), `stream_end` events on `private-chat.session.{sessionKey}`
5. After streaming completes, saves assistant message, fires `SessionCreated`/`SessionUpdated` on `private-chat.user.{userId}`
6. Frontend `ChatInterface` subscribes to session channel via Laravel Echo, accumulates text deltas into React state
7. `ChatLayout` subscribes to user channel — sidebar auto-refreshes on session lifecycle events

### Core Services (`app/Services/`)
- `Agent/AgentRuntime` — Main orchestrator: sync (`handleMessage`), SSE (`streamMessage`), WebSocket (`streamAndBroadcast`)
- `Agent/SessionResolver` — Maps `IncomingMessage` to `AgentSession` (creates if needed, resolves default agent)
- `Agent/ContextAssembler` — Builds system prompt + conversation history, includes compacted summaries
- `Agent/SystemPromptBuilder` — Composes from workspace files with agent-level prompt overrides
- `Agent/ModelRegistry` — Available models filtered by configured API keys, resolves provider from model ID

### Models
- `Agent` — Agent configurations (multi-agent support, `is_default` flag, `prompt_overrides` JSON)
- `AgentSession` — Conversation sessions with channel, trust level, per-session model/provider override
- `AgentMessage` — Individual messages (append-only log, `usage` JSON for token tracking)
- `Memory` — Long-term memory entries + embeddings
- `Tool` / `ToolExecution` — Tool registry and audit log

### DCS Layout (Dual Carousel Sidebar)
Ported from LaRaDav. Glassmorphism + 5 OKLCH color schemes (crimson default, stone, ocean, forest, sunset).

- `Contexts/ThemeContext` — Theme/scheme/sidebar state, persisted to localStorage key `laraclaw-state`
- `Components/PanelCarousel` — Sliding `< [dot·dot] >` carousel for panels
- `Components/Sidebar` — Glass sidebar shell with pin/unpin, auto-unpins below 1280px
- Left panels: Navigation (L1), Conversations (L2), Agent Config (L3)
- Right panels: Theme (R1), Usage Stats (R2), Settings (R3)
- CSS: `resources/css/laraclaw.css` — OKLCH scheme vars, glassmorphism, sidebar transitions
- CSS: `resources/css/app.css` — Bridges scheme vars to Tailwind theme tokens (`--color-*`)

### Config
- `config/agent.php` — Models, providers, pricing, workspace settings, session defaults
- `config/channels.php` — Web/TUI/Email channel configs with trust levels
- `config/tools.php` — Tool policies per trust level (operator/standard/restricted)
- `config/memory.php` — Embedding and search settings

### Workspace Files (Agent Prompts)
- `storage/app/agent/AGENTS.md` — Core agent instructions
- `storage/app/agent/SOUL.md` — Personality (optional)
- `storage/app/agent/TOOLS.md` — Tool conventions (optional)
- `storage/app/agent/MEMORY.md` — Curated long-term facts (optional)
- `storage/app/agent/skills/` — Skill playbooks

## Environment

- `ANTHROPIC_API_KEY` — required for default provider
- `OPENAI_API_KEY`, `GEMINI_API_KEY`, `GROQ_API_KEY`, `XAI_API_KEY`, `DEEPSEEK_API_KEY`, `OPENROUTER_API_KEY` — optional
- `AI_DEFAULT_PROVIDER=anthropic`, `AI_DEFAULT_MODEL=claude-sonnet-4-5-20250929`
- Login: markc@renta.net / changeme_N0W

## Conventions

- PascalCase directories: Pages/, Components/, Layouts/, Contexts/ (Breeze convention)
- Services in app/Services/ organized by domain
- DTOs in app/DTOs/
- Config-driven: use `config()` not `env()` outside config files
- Theme CSS vars defined in `laraclaw.css`, bridged to Tailwind in `app.css` via `@theme` block
- TypeScript types for shared Inertia props in `resources/js/types/index.d.ts`
- Streaming uses Reverb WebSocket via Laravel AI SDK `broadcastNow()` — frontend listens via Laravel Echo
- Tests use Pest with in-memory SQLite (`phpunit.xml` configures `DB_DATABASE=:memory:`)
- Package manager: Bun (not npm) — use `bun install`, `bun run build`, `bunx` for CLI tools
