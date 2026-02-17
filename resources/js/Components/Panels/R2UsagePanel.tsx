import { usePage } from '@inertiajs/react';
import { Cpu, DollarSign, Hash, MessageSquare } from 'lucide-react';
import type { PageProps } from '@/types';

function formatTokens(n: number): string {
    if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`;
    if (n >= 1_000) return `${(n / 1_000).toFixed(1)}K`;
    return String(n);
}

function StatCard({ icon: Icon, label, value, sub }: { icon: typeof MessageSquare; label: string; value: string; sub?: string }) {
    return (
        <div className="rounded-lg border p-3" style={{ borderColor: 'var(--glass-border)', background: 'var(--glass)' }}>
            <div className="flex items-center gap-2">
                <Icon className="h-3.5 w-3.5" style={{ color: 'var(--scheme-accent)' }} />
                <span className="text-xs" style={{ color: 'var(--scheme-fg-muted)' }}>{label}</span>
            </div>
            <div className="mt-1 text-lg font-semibold" style={{ color: 'var(--scheme-fg-primary)' }}>{value}</div>
            {sub && <div className="mt-0.5 text-[10px]" style={{ color: 'var(--scheme-fg-muted)' }}>{sub}</div>}
        </div>
    );
}

export default function R2UsagePanel() {
    const { sidebarStats } = usePage<PageProps>().props;

    if (!sidebarStats) {
        return (
            <div className="flex h-32 items-center justify-center">
                <span className="text-xs" style={{ color: 'var(--scheme-fg-muted)' }}>No usage data yet</span>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-4 p-4">
            <div className="grid grid-cols-2 gap-2">
                <StatCard icon={MessageSquare} label="Chats" value={String(sidebarStats.conversations)} />
                <StatCard icon={Hash} label="Messages" value={String(sidebarStats.messages)} />
            </div>
        </div>
    );
}
