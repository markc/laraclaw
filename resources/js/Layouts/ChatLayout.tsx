import { router, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { useEffect, type PropsWithChildren } from 'react';
import Sidebar from '@/Components/Sidebar';
import L1NavPanel from '@/Components/Panels/L1NavPanel';
import L2ConversationsPanel from '@/Components/Panels/L2ConversationsPanel';
import L3AgentPanel from '@/Components/Panels/L3AgentPanel';
import R1ThemePanel from '@/Components/Panels/R1ThemePanel';
import R2UsagePanel from '@/Components/Panels/R2UsagePanel';
import R3SettingsPanel from '@/Components/Panels/R3SettingsPanel';
import TopNav from '@/Components/TopNav';
import { ThemeProvider, useTheme } from '@/Contexts/ThemeContext';

const leftPanels = [
    { label: 'L1: Navigation', content: <L1NavPanel /> },
    { label: 'L2: Conversations', content: <L2ConversationsPanel /> },
    { label: 'L3: Agent', content: <L3AgentPanel /> },
];
const rightPanels = [
    { label: 'R1: Theme', content: <R1ThemePanel /> },
    { label: 'R2: Usage', content: <R2UsagePanel /> },
    { label: 'R3: Settings', content: <R3SettingsPanel /> },
];

function LayoutContent({ children }: PropsWithChildren) {
    const { left, right, noPadding, toggleSidebar } = useTheme();
    const { auth } = usePage().props;

    useEffect(() => {
        const onScroll = () => document.body.classList.toggle('scrolled', window.scrollY > 0);
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    // Subscribe to user-level channel for session lifecycle events
    useEffect(() => {
        if (!auth?.user?.id) return;

        const channel = window.Echo.private(`chat.user.${auth.user.id}`);

        const reloadSidebar = () => {
            router.reload({ only: ['sidebarConversations', 'sidebarStats'] });
        };

        channel.listen('.session.created', reloadSidebar);
        channel.listen('.session.updated', reloadSidebar);
        channel.listen('.session.deleted', () => {
            // Only reload if we're on the index page — the delete response
            // already redirects to /chat with fresh props. Reloading on a
            // /chat/{id} page for a deleted session would 404.
            if (window.location.pathname === '/chat') {
                reloadSidebar();
            }
        });

        return () => {
            window.Echo.leave(`chat.user.${auth.user.id}`);
        };
    }, [auth?.user?.id]);

    return (
        <div className="bg-background text-foreground">
            <button
                onClick={() => toggleSidebar('left')}
                className="fixed top-[0.625rem] left-3 z-50 rounded-lg p-1.5 text-foreground transition-colors hover:text-[var(--scheme-accent)]"
                style={{
                    background: 'var(--glass)',
                    backdropFilter: 'blur(20px)',
                    WebkitBackdropFilter: 'blur(20px)',
                    border: '1px solid var(--glass-border)',
                }}
                aria-label="Toggle left sidebar"
            >
                <Menu className="h-5 w-5" />
            </button>
            <button
                onClick={() => toggleSidebar('right')}
                className="fixed top-[0.625rem] right-3 z-50 rounded-lg p-1.5 text-foreground transition-colors hover:text-[var(--scheme-accent)]"
                style={{
                    background: 'var(--glass)',
                    backdropFilter: 'blur(20px)',
                    WebkitBackdropFilter: 'blur(20px)',
                    border: '1px solid var(--glass-border)',
                }}
                aria-label="Toggle right sidebar"
            >
                <Menu className="h-5 w-5" />
            </button>

            <Sidebar side="left" panels={leftPanels} />
            <Sidebar side="right" panels={rightPanels} />

            <TopNav />

            <div
                className={`sidebar-slide ${noPadding ? '' : 'min-h-screen'}`}
                style={{
                    marginInlineStart: left.pinned ? 'var(--sidebar-width)' : undefined,
                    marginInlineEnd: right.pinned ? 'var(--sidebar-width)' : undefined,
                }}
            >
                <main key={usePage().url} className={`page-fade-in ${noPadding ? '' : 'px-2 py-4 sm:p-4'}`}>
                    {children}
                </main>
            </div>
        </div>
    );
}

export default function ChatLayout({ children }: PropsWithChildren) {
    return (
        <ThemeProvider>
            <LayoutContent>{children}</LayoutContent>
        </ThemeProvider>
    );
}
