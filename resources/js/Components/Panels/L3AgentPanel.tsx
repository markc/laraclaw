import { Bot, Cpu, FileText } from 'lucide-react';

export default function L3AgentPanel() {
    return (
        <div className="flex flex-col gap-4 p-4">
            <div className="flex items-center gap-2">
                <Bot className="h-4 w-4" style={{ color: 'var(--scheme-accent)' }} />
                <h2 className="text-sm font-semibold" style={{ color: 'var(--scheme-fg-primary)' }}>
                    Agent Config
                </h2>
            </div>

            <div className="rounded-lg border p-3" style={{ borderColor: 'var(--glass-border)', background: 'var(--glass)' }}>
                <div className="flex items-center gap-2">
                    <Cpu className="h-3.5 w-3.5" style={{ color: 'var(--scheme-accent)' }} />
                    <span className="text-xs" style={{ color: 'var(--scheme-fg-muted)' }}>Default Agent</span>
                </div>
                <div className="mt-1 text-sm font-medium" style={{ color: 'var(--scheme-fg-primary)' }}>
                    LaRaClaw
                </div>
                <p className="mt-1 text-xs" style={{ color: 'var(--scheme-fg-muted)' }}>
                    Self-hosted AI agent with tool use, memory, and multi-provider support.
                </p>
            </div>

            <div className="rounded-lg border p-3" style={{ borderColor: 'var(--glass-border)', background: 'var(--glass)' }}>
                <div className="flex items-center gap-2">
                    <FileText className="h-3.5 w-3.5" style={{ color: 'var(--scheme-accent)' }} />
                    <span className="text-xs" style={{ color: 'var(--scheme-fg-muted)' }}>Workspace Files</span>
                </div>
                <ul className="mt-2 space-y-1 text-xs" style={{ color: 'var(--scheme-fg-secondary)' }}>
                    <li>AGENTS.md — Core instructions</li>
                    <li>SOUL.md — Personality</li>
                    <li>TOOLS.md — Tool conventions</li>
                    <li>skills/ — Skill playbooks</li>
                </ul>
            </div>
        </div>
    );
}
