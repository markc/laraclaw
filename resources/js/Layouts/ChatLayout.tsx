import { Link, router, usePage } from '@inertiajs/react';
import { PropsWithChildren, useState } from 'react';
import { AgentSession, PageProps } from '@/types';

function ConversationItem({ session, isActive }: { session: AgentSession; isActive: boolean }) {
    return (
        <Link
            href={route('chat.show', session.id)}
            className={`block truncate rounded px-3 py-2 text-sm transition ${
                isActive
                    ? 'bg-indigo-100 text-indigo-900 dark:bg-indigo-900/30 dark:text-indigo-200'
                    : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'
            }`}
        >
            {session.title}
        </Link>
    );
}

export default function ChatLayout({
    children,
    currentSessionId,
}: PropsWithChildren<{ currentSessionId?: number }>) {
    const { auth, sidebarConversations, sidebarStats } = usePage<PageProps>().props;
    const [sidebarOpen, setSidebarOpen] = useState(true);

    return (
        <div className="flex h-screen bg-gray-50 dark:bg-gray-900">
            {/* Sidebar */}
            <aside
                className={`flex flex-col border-r border-gray-200 bg-white transition-all dark:border-gray-700 dark:bg-gray-800 ${
                    sidebarOpen ? 'w-72' : 'w-0 overflow-hidden'
                }`}
            >
                {/* Header */}
                <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <h1 className="text-lg font-semibold text-gray-900 dark:text-white">LaRaClaw</h1>
                    <Link
                        href={route('chat.index')}
                        className="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
                    >
                        + New
                    </Link>
                </div>

                {/* Conversation list */}
                <div className="flex-1 overflow-y-auto px-2 py-2">
                    {sidebarConversations.length === 0 ? (
                        <p className="px-3 py-4 text-center text-sm text-gray-400">No conversations yet</p>
                    ) : (
                        <div className="space-y-0.5">
                            {sidebarConversations.map((s) => (
                                <ConversationItem key={s.id} session={s} isActive={s.id === currentSessionId} />
                            ))}
                        </div>
                    )}
                </div>

                {/* Footer with stats and user */}
                <div className="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                    {sidebarStats && (
                        <div className="mb-2 flex gap-4 text-xs text-gray-400">
                            <span>{sidebarStats.conversations} chats</span>
                            <span>{sidebarStats.messages} msgs</span>
                        </div>
                    )}
                    <div className="flex items-center justify-between">
                        <span className="truncate text-sm text-gray-600 dark:text-gray-300">{auth.user.name}</span>
                        <div className="flex gap-2">
                            <Link
                                href={route('profile.edit')}
                                className="text-xs text-gray-400 hover:text-gray-600"
                            >
                                Settings
                            </Link>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="text-xs text-gray-400 hover:text-red-600"
                            >
                                Logout
                            </Link>
                        </div>
                    </div>
                </div>
            </aside>

            {/* Main content */}
            <div className="flex flex-1 flex-col">
                {/* Toggle sidebar button */}
                <button
                    onClick={() => setSidebarOpen(!sidebarOpen)}
                    className="absolute left-2 top-2 z-10 rounded-md p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-700 sm:hidden"
                >
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                {children}
            </div>
        </div>
    );
}
