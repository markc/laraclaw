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
php artisan agent:health-check  # Run health checks, dispatch matching health-type routines
php artisan sandbox:provision   # Clone ready Proxmox containers for sandboxed execution
php artisan sandbox:cleanup     # Release stale containers (--destroy-all to remove all)
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
- `Agent/AgentRuntime` — Main orchestrator: sync (`handleMessage`), SSE (`streamMessage`), WebSocket (`streamAndBroadcast`). Integrates `IntentRouter` for early command short-circuiting before LLM calls.
- `Agent/SessionResolver` — Maps `IncomingMessage` to `AgentSession` (creates if needed, resolves default agent)
- `Agent/ContextAssembler` — Builds system prompt + conversation history, includes compacted summaries + relevant memories from hybrid search
- `Agent/SystemPromptBuilder` — Composes from workspace files with agent-level prompt overrides + delimiter instructions for injection defense
- `Agent/ModelRegistry` — Available models filtered by configured API keys, resolves provider from model ID
- `Agent/IntentRouter` — Classifies messages as Command/Query/Task. Slash commands (`/model`, `/help`, `/rename`, `/info`, `/new`) are handled without LLM calls. Registered as singleton.
- `Memory/EmbeddingService` — HTTP client to Ollama `/api/embed` (nomic-embed-text, 768-dim). Methods: `embed()`, `embedBatch()`, `toVector()`, `isAvailable()`
- `Memory/MemorySearchService` — Hybrid search: pgvector cosine distance + tsvector full-text, fused via Reciprocal Rank Fusion (RRF)
- `Security/ContentSanitizer` — Scans content for 8 prompt injection patterns, applies policy (block/warn/sanitize/allow), wraps in delimiters
- `Security/InjectionAuditLog` — Dual logging to Laravel log + `injection_detections` table
- `Tools/SanitizingToolWrapper` — Transparent decorator: wraps any Tool, sanitizes output, logs detections
- `Routines/EventFilterEvaluator` — Evaluates event filters (exact match, glob, existence checks) for event-triggered routines
- `Routines/HealthMonitor` — Checks stuck jobs, failed jobs, missed heartbeats, stale sessions
- `Sandbox/ProxmoxClient` — HTTP client for Proxmox VE REST API (container lifecycle, exec)
- `Sandbox/ContainerPool` — Atomic claim/release of pooled Proxmox containers via `lockForUpdate()`
- `Sandbox/SandboxExecutor` — Claim → exec → release workflow for sandboxed command execution

### Models
- `Agent` — Agent configurations (multi-agent support, `is_default` flag, `prompt_overrides` JSON)
- `AgentSession` — Conversation sessions with channel, trust level, per-session model/provider override
- `AgentMessage` — Individual messages (append-only log, `usage` JSON for token tracking)
- `Memory` — Long-term memory entries + embeddings. Auto-dispatches `GenerateMemoryEmbedding` job on create/update (gated by config)
- `Tool` / `ToolExecution` — Tool registry and audit log
- `InjectionDetection` — Audit log for prompt injection detections (source, patterns, policy applied)
- `ScheduledAction` — Routines with 4 trigger types: Cron, Event, Webhook, Health. Supports cooldowns, retry tracking, event filters
- `SandboxContainer` — Pooled Proxmox CT containers (vmid, status: ready/busy/provisioning/destroying)

### Intent Router & Slash Commands

Messages are classified before reaching the LLM. Slash commands short-circuit entirely — no tokens consumed.

**Built-in commands** (`app/Services/Agent/Commands/`):
- `/model [id]` — Show current model or switch to a new one. Validates against `ModelRegistry`, updates session.
- `/rename <title>` — Rename the current session. Fires `SessionUpdated` event.
- `/help` (alias: `/?`) — List all registered commands with descriptions.
- `/info` — Show session details: model, provider, message count, tool count, trust level.
- `/new` — Start a fresh session (returns metadata for caller to handle per-channel).

**TUI-only commands** (handled locally in `AgentChat` before IntentRouter):
- `/quit`, `/exit`, `/q` — Exit the TUI chat loop.
- `/sessions` — List recent TUI sessions.
- `/resume <key>` — Resume a previous session.

**Adding custom commands**: Create a class implementing `App\Contracts\CommandHandler`, register it in `config/intents.php` under the `commands` key.

**Heuristic classification** (non-command messages): Messages are classified as `Query` or `Task` based on length, question marks, interrogative words, code blocks, and imperative verbs. No LLM call — pure heuristics. Config thresholds in `config/intents.php`.

### Hybrid Memory Search (RRF)

Memories are automatically embedded via Ollama and searchable via hybrid vector+keyword search.

**How it works**:
1. `Memory` model dispatches `GenerateMemoryEmbedding` job on create/update (when `config('memory.auto_index.enabled')`)
2. Job calls Ollama `/api/embed` → stores 768-dim vector via `DB::statement()` with `::vector` cast
3. PostgreSQL trigger auto-populates `content_tsv` tsvector column from `content`
4. `ContextAssembler` calls `MemorySearchService::search()` with the user's message
5. Vector search (pgvector `<=>`) and keyword search (tsvector/tsquery) run in parallel
6. Results merged via RRF: `score = Σ(weight / (k + rank))` with k=60, vector_weight=0.7, keyword_weight=0.3
7. Top results appended to system prompt as `## Relevant Memories` section

**Graceful degradation**: If Ollama is unavailable, keyword-only results are returned. If search fails entirely, chat continues without memory context.

**Config** (`config/memory.php`): `search.enabled`, `search.rrf_k`, `search.candidate_multiplier`, `embedding.timeout`, `embedding.max_content_length`, `auto_index.enabled`

### Prompt Injection Defense

External content (tool outputs, emails, webhooks) is scanned and sandboxed before entering LLM context.

**Detection patterns** (8 regex + base64 recursive scan):
`ignore_instructions`, `new_instructions`, `role_reassignment`, `role_impersonation`, `special_tokens`, `html_script_injection`, `act_as`, `instruction_override`

**Policy matrix** (`config/security.php`): source × trust_level → action
- `Block` — Replace content with `[Content blocked]`
- `Warn` — Prepend warning, preserve content
- `Sanitize` — Redact matched patterns with `[REDACTED]`
- `Allow` — Pass through with delimiter wrapping only

**Delimiter wrapping**: All external content wrapped in `<<<SOURCE_TYPE>>>...<<<END_SOURCE_TYPE>>>` markers. System prompt instructs the model to treat delimited content as untrusted data, not instructions.

**Integration points**:
- `SanitizingToolWrapper` — Automatically wraps all tools when `config('security.sanitizer.enabled')` (applied in `ToolResolver`)
- `ProcessEmailMessage` — Sanitizes email body before passing to `AgentRuntime`
- `SystemPromptBuilder` — Appends delimiter handling instructions

**Audit**: Detections logged to both Laravel log and `injection_detections` table via `InjectionAuditLog`.

### Background Routines Engine

Extends `ScheduledAction` from cron-only to 4 trigger types.

**Trigger types** (`App\Enums\TriggerType`):
- `Cron` — Existing heartbeat-based scheduling (`agent:heartbeat` scoped to cron-only)
- `Event` — Fires on Laravel events. Wildcard listener (`RoutineEventDispatcher`) matches `event_class`, evaluates `event_filter` JSON, checks cooldown.
- `Webhook` — `POST /api/routines/webhook/{token}` endpoint. Token-authenticated, returns 202.
- `Health` — `agent:health-check` command (runs every 5 min). Checks: stuck jobs, failed jobs, missed heartbeats, stale sessions.

**Event filters** (`EventFilterEvaluator`): JSON conditions with exact match, glob wildcards (`fnmatch`), existence checks (`__exists__`), nested dot notation. All conditions are AND logic.

**Retry tracking**: `max_retries`, `retry_count`, `last_error`. Auto-disables routine when retries exhausted.

**Cooldowns**: `cooldown_seconds` prevents re-triggering within the cooldown window.

### Proxmox CT Sandboxing

Optional sandboxed code execution via Proxmox container pool. Disabled by default (`config('sandbox.driver') = 'none'`).

**Architecture**: `ContainerPool` manages a fleet of pre-cloned lightweight containers. `SandboxExecutor` claims a container → runs command via Proxmox API exec → releases it. `SandboxedBashTool` is a drop-in replacement for `BashTool` routed through the executor.

**Setup**:
1. Set `SANDBOX_DRIVER=proxmox` and configure `PROXMOX_*` env vars
2. Create a template container in Proxmox with required tools
3. Run `php artisan sandbox:provision` to clone ready containers
4. `ToolResolver` automatically swaps `BashTool` for `SandboxedBashTool`

**Commands**: `sandbox:provision {count=2}`, `sandbox:cleanup [--destroy-all]`

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
- `config/memory.php` — Embedding, search (RRF), and auto-indexing settings
- `config/intents.php` — Slash command → handler class map, heuristic thresholds
- `config/security.php` — Content sanitizer toggle, detection logging, policy matrix (source × trust → action)
- `config/sandbox.php` — Proxmox CT sandboxing driver, API credentials, container pool settings

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
- Services in app/Services/ organized by domain (Agent/, Memory/, Security/, Routines/, Sandbox/, Tools/)
- DTOs in app/DTOs/ (`IncomingMessage`, `ClassifiedIntent`, `SanitizeResult`)
- Enums in app/Enums/ (`IntentType`, `TriggerType`, `SanitizePolicy`, `ContentSource`)
- Contracts in app/Contracts/ (`CommandHandler` for slash commands)
- Config-driven: use `config()` not `env()` outside config files
- Theme CSS vars defined in `laraclaw.css`, bridged to Tailwind in `app.css` via `@theme` block
- TypeScript types for shared Inertia props in `resources/js/types/index.d.ts`
- Streaming uses Reverb WebSocket via Laravel AI SDK `broadcastNow()` — frontend listens via Laravel Echo
- Tests use Pest with in-memory SQLite (`phpunit.xml` configures `DB_DATABASE=:memory:`)
- Package manager: Bun (not npm) — use `bun install`, `bun run build`, `bunx` for CLI tools

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5.3
- inertiajs/inertia-laravel (INERTIA) - v2
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/sanctum (SANCTUM) - v4
- tightenco/ziggy (ZIGGY) - v2
- laravel/boost (BOOST) - v2
- laravel/breeze (BREEZE) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- phpunit/phpunit (PHPUNIT) - v11
- laravel-echo (ECHO) - v2
- @inertiajs/react (INERTIA) - v2
- react (REACT) - v18
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `pest-testing` — Tests applications using the Pest 3 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, architecture testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `inertia-react-development` — Develops Inertia.js v2 React client-side applications. Activates when creating React pages, forms, or navigation; using &lt;Link&gt;, &lt;Form&gt;, useForm, or router; working with deferred props, prefetching, or polling; or when user mentions React with Inertia, React pages, React forms, or React navigation.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `bun run build`, `bun run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

=== inertia-laravel/v2 rules ===

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scrolling (merging props + `WhenVisible`), lazy loading on scroll, polling, prefetching.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `bun run build` or ask the user to run `bun run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

</laravel-boost-guidelines>
