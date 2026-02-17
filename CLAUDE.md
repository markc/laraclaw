# CLAUDE.md — LaRaClaw

## Project Overview

LaRaClaw is a self-hosted AI agent platform — an OpenClaw reimplementation in Laravel.
Three interaction channels: Web Chat (Inertia/React), TUI (Laravel Prompts), Email.

## Tech Stack

- PHP 8.4+, Laravel 12, Inertia 2, React 19, TypeScript, Tailwind CSS 4
- Laravel/AI SDK for multi-provider LLM abstraction
- Laravel Reverb for WebSocket (real-time streaming)
- SQLite (dev), PostgreSQL + pgvector (production)
- Vite 7, @laravel/stream-react for SSE streaming

## Commands

```bash
composer dev                    # Start server + queue + logs + Vite
npm run build                   # Build frontend assets
php artisan migrate             # Run migrations
php artisan migrate:fresh --seed # Reset database with default agent + user
php artisan db:seed             # Seed default agent
```

## Architecture

### Core Services (`app/Services/`)
- `Agent/AgentRuntime` — Main orchestrator: receive message → resolve session → assemble context → invoke AI → persist
- `Agent/SessionResolver` — Maps channel input to session (creates if needed)
- `Agent/ContextAssembler` — Builds system prompt + conversation history
- `Agent/SystemPromptBuilder` — Composes from AGENTS.md/SOUL.md/TOOLS.md/skills/
- `Agent/ModelRegistry` — Available models filtered by configured API keys

### Models
- `Agent` — Agent configurations (multi-agent support)
- `AgentSession` — Conversation sessions with channel, trust level, model
- `AgentMessage` — Individual messages (append-only log)
- `Memory` — Long-term memory entries + embeddings
- `Tool` / `ToolExecution` — Tool registry and audit log

### Config
- `config/agent.php` — Models, providers, pricing, workspace settings
- `config/channels.php` — Web/TUI/Email channel configs
- `config/tools.php` — Tool policies per trust level
- `config/memory.php` — Embedding and search settings

### Workspace Files
- `storage/app/agent/AGENTS.md` — Core agent instructions
- `storage/app/agent/SOUL.md` — Personality (optional)
- `storage/app/agent/TOOLS.md` — Tool conventions (optional)
- `storage/app/agent/skills/` — Skill playbooks

## Environment

- `ANTHROPIC_API_KEY` — required for default provider
- `OPENAI_API_KEY`, `GEMINI_API_KEY`, etc. — optional additional providers
- `AI_DEFAULT_PROVIDER=anthropic`
- Login: markc@renta.net / changeme_N0W

## Conventions

- PascalCase directories: Pages/, Components/, Layouts/ (Breeze convention)
- Services in app/Services/ organized by domain
- DTOs in app/DTOs/
- Config-driven: use config() not env() outside config files
