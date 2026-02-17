I'll fetch that page and then summarize it for you in a way that's actionable for Claude Code.## OpenClaw Architecture Summary for Laravel + Inertia + React Reimplementation

Here's a detailed breakdown organized to guide Claude Code in recreating OpenClaw as a Laravel application:

### **Core Architectural Pattern: Hub-and-Spoke Control Plane**

OpenClaw operates as an operating system for AI agents, not a chatbot wrapper. The central Gateway acts as a WebSocket server that:
- Connects to multiple messaging platforms (WhatsApp, Telegram, Discord, Slack, iMessage, Signal, Teams)
- Routes messages through access control, session resolution, and agent execution
- Manages persistent state, security boundaries, and tool sandboxing

For Laravel, this becomes a WebSocket server (using Laravel WebSockets or Laravel Reverb) with a control plane managing channel adapters, session routing, and agent coordination.

---

### **1. Channel Adapters (Messaging Integration)**

Each platform gets a dedicated adapter handling:
- **Authentication**: WhatsApp QR pairing (Baileys), Telegram/Discord bot tokens, iMessage native integration
- **Inbound parsing**: Extract text, media, thread context; normalize across platforms
- **Access control**: Allowlists (phone numbers/usernames), DM pairing policies, group mention requirements
- **Outbound formatting**: Platform-specific markdown, message chunking, media uploads, typing indicators

**Laravel Implementation:**
- Create `app/Channels/Adapters/` directory with classes per platform (WhatsappAdapter, TelegramAdapter, etc.)
- Implement shared `ChannelAdapterInterface` with `authenticate()`, `parseInbound()`, `validateAccess()`, `formatOutbound()`
- Store credentials in `~/.openclaw/credentials/` with 0600 permissions (or Laravel's encrypted credentials)
- Configuration driven by `config/channels.php` (allowlists, DM policies, group rules)

---

### **2. Gateway Control Plane (WebSocket Server)**

The Gateway is the single source of truth for:
- Session state (active conversations, presence, permissions)
- Health monitoring and system state
- Cron job and webhook trigger management
- Device pairing and authentication (challenge-response for remote connections)

**Laravel Implementation:**
- Use Laravel Reverb (or WebSockets package) to build WebSocket server in `app/Gateway/`
- Implement event-driven subscriptions: `agent`, `presence`, `health`, `tick` channels
- Create `app/Models/Session.php` with session resolution logic (main → `agent:<id>:main`, DM → `agent:<id>:<channel>:dm:<id>`, group → `agent:<id>:<channel>:group:<id>`)
- Token/password auth via middleware on WebSocket handshake
- Device pairing stored in database with cryptographic key exchange

---

### **3. Agent Runtime (Core Execution Loop)**

The runtime performs 4 steps per turn:
1. **Session Resolution**: Determine which session (main, DM, group) should handle the message
2. **Context Assembly**: Load session history from JSON files, build system prompt from configuration files (`AGENTS.md`, `SOUL.md`, `TOOLS.md`), inject relevant skills, semantic memory search
3. **Model Invocation**: Stream context to configured provider (Anthropic, OpenAI, Gemini, local)
4. **Tool Execution**: Intercept tool calls, execute (potentially sandboxed), stream results back to model
5. **State Persistence**: Save updated session to disk

**Laravel Implementation:**
- Create `app/Agent/AgentRuntime.php` with RPC-style invocation using Laravel/AI
- Session files in `storage/openclaw/sessions/<sessionId>.json` (append-only event log)
- System prompt composition in `app/Agent/SystemPromptBuilder.php` reading from `storage/openclaw/workspaces/<workspace>/`
- Use Laravel Blade or custom PHP for prompt templates
- Memory search using Laravel Scout with SQLite vector database
- Tool execution through `app/Tools/ToolExecutor.php` with Docker sandboxing support

**Tools Architecture:**
- Built-in tools: bash, browser (Chromium via CDP), file operations, Canvas, cron/webhooks
- Tool definitions auto-generated from `app/Tools/` classes
- Plugin system for custom tools via service provider registration
- Each tool implements `ToolInterface` with execute(), definition(), validate()

---

### **4. System Prompt Architecture**

Prompts compose from multiple layers:
- **AGENTS.md**: Core operational instructions (global, non-negotiable rules)
- **SOUL.md**: Personality and tone (optional)
- **TOOLS.md**: User-specific tool conventions (optional)
- **Skills/**: Playbooks for accomplishing specific tasks (skills/skill-name/SKILL.md)
- **Session history**: Recent messages from current conversation
- **Memory search**: Semantically similar past conversations via vector similarity + BM25
- **Tool definitions**: Auto-generated from registered tools
- **Base system**: From agent core library

**Laravel Implementation:**
- Create `storage/openclaw/workspaces/<workspace>/` directory structure with markdown files
- `app/Agent/PromptComposer.php` reads and assembles markdown files
- Skills selective injection: only inject relevant skills to current turn (avoid prompt bloat)
- Use Laravel Scout for memory search (SQLite vector backend with sqlite-vec)
- Build final prompt as system message + conversation history + current turn

---

### **5. Control Interfaces**

Multiple ways to interact with the system:
- **Web UI**: Lit-based components served from Gateway (default `127.0.0.1:18789`)
- **CLI**: Commander.js style commands (`openclaw gateway`, `openclaw agent`, `openclaw channels login`, `openclaw message send`)
- **macOS app**: Swift menu bar app with Gateway lifecycle management, WebChat embedding, Voice Wake
- **Mobile**: iOS/Android apps as WebSocket nodes with device-specific capabilities (camera, location, screen recording)

**Laravel Implementation:**
- Web UI: Inertia + React application at `resources/js/` served from Laravel
- CLI: Use `artisan` commands in `app/Commands/` (GatewayCommand, AgentCommand, ChannelsCommand, etc.)
- API routes in `routes/api.php` for WebSocket authentication handshake
- Mobile support: WebSocket protocol for device identity and node invocation

---

### **6. Canvas and Agent-to-UI (A2UI)**

Agent-driven visual workspace running as separate server (port 18793 default):
- Agent calls canvas update method with HTML content
- Canvas server embeds A2UI attributes (special HTML attributes) in the HTML
- Browser clients receive updates via WebSocket
- User interactions (button clicks) trigger tool calls back to agent

A2UI provides declarative framework where agents generate interactive HTML without writing JavaScript:
```html
<button a2ui-action="complete" a2ui-param-id="123">
  Mark Complete
</button>
```

**Laravel Implementation:**
- Separate Canvas service in `app/Canvas/` or as standalone Reverb application
- Inertia/React component receives Canvas updates and renders HTML
- Button clicks trigger WebSocket events back to agent
- Cross-platform support: web UI, macOS WebKit, iOS Swift UI, Android WebView

---

### **7. Security Architecture**

**Network Security:**
- Loopback only by default (`127.0.0.1:18789`)
- Remote access via SSH tunnel or Tailscale (Serve for tailnet-only, Funnel for public)

**Authentication & Device Pairing:**
- Token/password auth for non-loopback
- Device identity (device ID + cryptographic keys) for all WebSocket clients
- Local connections auto-approve; remote require challenge-response signing
- Device tokens issued after approval

**Channel Access Control:**
- DM pairing: unknown senders get pairing code requiring approval
- Allowlists: explicit phone numbers/usernames allowed
- Group policies: require mention, group-specific allowlists

**Tool Sandboxing:**
- Main session: full host access (no Docker)
- DM/group sessions: Docker-based sandboxing by default
- Per-session security boundaries in configuration
- Tool policy precedence: Tool Profile → Provider Profile → Global → Provider → Agent → Group → Sandbox

**Prompt Injection Defense:**
- Context isolation: user messages separate from system instructions
- Structured tool result wrapping
- Model selection: latest-gen models for tool-enabled bots
- Hard controls: access control, tool policy restrictions, sandboxing

**Laravel Implementation:**
- Middleware for token/password authentication
- Device pairing in database with crypto package
- Policy-based authorization for tool execution
- Docker wrapper in `app/Tools/Sandbox.php`
- Session-based policy enforcement in AgentRuntime

---

### **8. Data Storage & State Management**

**Directory Structure** (`~/.openclaw/` or `storage/openclaw/`):
- `openclaw.json` (JSON5): Main config with comments/trailing commas
- `sessions/`: Session files as append-only JSON event logs
- `workspaces/`: Markdown files (AGENTS.md, SOUL.md, TOOLS.md, skills/, memory/)
- `memory/`: SQLite vector database for semantic search
- `credentials/`: Authentication tokens (0600 permissions, excluded from version control)

**Session State:**
- Append-only event log supporting branching and recovery
- Automatic compaction: older conversations summarized to stay within context limits
- Memory flush: promotes important info to memory files before compaction

**Memory Search:**
- Hybrid search: vector similarity (semantic) + BM25 (keyword)
- Embedding provider selection: local model → OpenAI → Gemini → disabled
- Automatic reindexing on file changes (1.5s debounce)
- Optional session transcript indexing

**Laravel Implementation:**
- Configuration in `config/openclaw.php` with environment variable overrides
- Sessions as JSON files in `storage/openclaw/sessions/` or database records
- Memory in SQLite via Laravel Scout with custom driver
- Credentials encrypted in `storage/openclaw/credentials/` with Laravel Encryption

---

### **9. Multi-Agent Routing**

Direct different channels/groups to isolated agent instances:
```json
{
  "agents": {
    "mapping": {
      "group:discord:123456": {
        "workspace": "~/.openclaw/workspaces/discord-bot",
        "model": "anthropic/claude-sonnet-4-5",
        "systemPromptOverrides": {
          "SOUL.md": "You are a helpful Discord moderator..."
        }
      },
      "dm:telegram:*": {
        "workspace": "~/.openclaw/workspaces/support-agent",
        "model": "openai/gpt-4o",
        "sandbox": { "mode": "always" }
      }
    }
  }
}
```

Each instance has isolated workspace, model, behavior, and tool access.

**Laravel Implementation:**
- `AgentResolver.php` determines which agent handles each session
- Agent configuration in database or config files
- Separate runtime instances per agent (process spawning or service dispatch)

---

### **10. End-to-End Message Flow (6 Phases)**

1. **Ingestion**: Channel adapter receives and parses message
2. **Access Control & Routing**: Check allowlist/pairing, resolve session
3. **Context Assembly**: Load history, build prompt, search memory
4. **Model Invocation**: Stream context to LLM provider
5. **Tool Execution**: Intercept and execute tool calls (potentially sandboxed)
6. **Response Delivery**: Format and deliver response through channel, persist state

**Latency Budget:**
- Access control: <10ms
- Load session: <50ms
- Assemble prompt: <100ms
- First token: 200-500ms
- Tool execution: 100ms (bash) to 1-3s (browser)

---

### **11. Scheduled Actions & Webhooks**

- **Cron jobs**: Trigger agent actions at specific times
- **Webhooks**: External trigger points (e.g., Gmail publishing to webhook triggers agent)
- Configuration-based setup for automation without custom code

**Laravel Implementation:**
- Schedule cron jobs in `app/Console/Kernel.php`
- Webhook receivers in routes with proper authentication
- Trigger session message injection on schedule/webhook

---

### **12. Deployment Architectures**

1. **Local Development**: Gateway on localhost with `pnpm dev` hot reload
2. **Production macOS**: LaunchAgent background service + menu bar app
3. **Linux/VPS**: systemd service with SSH tunnel or Tailscale Serve access
4. **Fly.io Container**: Docker image with persistent volume for state

**Laravel Implementation:**
- Local: `php artisan serve` with WebSocket server
- Production: Supervisor for daemon management
- VPS: systemd service file with Laravel as service
- Container: Docker with persistent volumes for sessions/credentials/memory

---

### **Key Design Patterns for Laravel/Inertia/React Implementation**

1. **Hub-and-Spoke**: Single Laravel application as control plane + Reverb for WebSocket
2. **Adapter Pattern**: Channel classes implementing common interface
3. **Plugin Architecture**: Service providers for extensibility
4. **Event-Driven**: Laravel Events/Broadcasting for real-time updates
5. **Configuration Over Code**: JSON config files, not hardcoding
6. **Security Layers**: Authentication middleware, policy authorization, sandboxing wrapper
7. **Composable Prompts**: File-based prompt assembly with semantic memory injection
8. **Isolated Sessions**: Per-session security boundaries and workspace isolation

This architecture remains true to OpenClaw's operating-system-for-AI-agents philosophy while implementing it in Laravel's ecosystem with Inertia/React for the frontend.
