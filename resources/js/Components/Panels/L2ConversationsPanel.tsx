import { Link, router, usePage } from '@inertiajs/react';
import { MessageSquare, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { AgentSession, PageProps } from '@/types';

export default function L2ConversationsPanel() {
    const { sidebarConversations } = usePage<PageProps>().props;
    const conversations = sidebarConversations ?? [];
    const [query, setQuery] = useState('');

    const match = (typeof window !== 'undefined' ? window.location.pathname : '').match(/\/chat\/(\d+)/);
    const currentId = match ? Number(match[1]) : undefined;

    const filtered = query
        ? conversations.filter(c => c.title.toLowerCase().includes(query.toLowerCase()))
        : conversations;

    return (
        <div className="flex h-full flex-col">
            <div className="flex items-center gap-2 px-3 py-2">
                <div className="relative flex-1">
                    <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2" style={{ color: 'var(--scheme-fg-muted)' }} />
                    <input
                        type="text"
                        value={query}
                        onChange={e => setQuery(e.target.value)}
                        placeholder="Search..."
                        className="w-full rounded-lg border bg-transparent py-1.5 pl-8 pr-3 text-xs outline-none placeholder:text-muted-foreground focus:border-[var(--scheme-accent)]"
                    />
                </div>
                <Link
                    href={route('chat.index')}
                    className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-white transition-opacity hover:opacity-80"
                    style={{ backgroundColor: 'var(--scheme-accent)' }}
                >
                    <Plus className="h-4 w-4" />
                </Link>
            </div>

            <div className="flex-1 overflow-y-auto">
                {filtered.length === 0 && (
                    <p className="p-3 text-center text-xs" style={{ color: 'var(--scheme-fg-muted)' }}>
                        {query ? 'No matches' : 'No conversations yet'}
                    </p>
                )}
                {filtered.map(conv => (
                    <div key={conv.id} className="group relative">
                        <Link
                            href={route('chat.show', conv.id)}
                            className={`flex items-center gap-2 px-3 py-2 text-sm transition-colors hover:bg-background ${
                                currentId === conv.id ? 'bg-background' : ''
                            }`}
                        >
                            <MessageSquare className="h-3.5 w-3.5 shrink-0" />
                            <span className="truncate">{conv.title}</span>
                        </Link>
                        <div className="absolute right-1 top-1/2 flex -translate-y-1/2 gap-0.5 rounded-lg border bg-background px-1 py-0.5 shadow-sm opacity-0 transition-opacity group-hover:opacity-100">
                            <button
                                onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    router.delete(route('chat.destroy', conv.id), { preserveScroll: true });
                                }}
                                className="rounded p-1 hover:bg-destructive/10"
                                title="Delete conversation"
                            >
                                <Trash2 className="h-3.5 w-3.5 text-muted-foreground hover:text-destructive" />
                            </button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
