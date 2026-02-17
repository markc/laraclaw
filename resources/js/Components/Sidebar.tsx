import { type ReactNode } from 'react';
import { Pin, PinOff } from 'lucide-react';
import PanelCarousel from '@/Components/PanelCarousel';
import { useTheme } from '@/Contexts/ThemeContext';

interface SidebarProps {
    side: 'left' | 'right';
    panels: { label: string; content: ReactNode }[];
}

export default function Sidebar({ side, panels }: SidebarProps) {
    const theme = useTheme();
    const state = theme[side];

    return (
        <aside
            className={`sidebar-${side} sidebar-slide fixed top-0 z-40 flex h-screen w-[var(--sidebar-width)] flex-col ${
                side === 'left' ? 'left-0' : 'right-0'
            } ${
                state.open ? 'translate-x-0' : side === 'left' ? '-translate-x-full' : 'translate-x-full'
            }`}
            style={{
                background: 'var(--glass)',
                backdropFilter: 'blur(20px)',
                WebkitBackdropFilter: 'blur(20px)',
            }}
        >
            <PanelCarousel
                panels={panels}
                activePanel={state.panel}
                onPanelChange={(i) => theme.setPanel(side, i)}
                side={side}
                headerSlot={
                    <button
                        onClick={() => theme.pinSidebar(side)}
                        className="hidden rounded p-1 transition-colors hover:bg-background xl:block"
                        style={{ color: state.pinned ? 'var(--scheme-accent)' : 'var(--scheme-fg-muted)' }}
                        aria-label={state.pinned ? 'Unpin sidebar' : 'Pin sidebar'}
                    >
                        {state.pinned ? <PinOff className="h-3.5 w-3.5" /> : <Pin className="h-3.5 w-3.5" />}
                    </button>
                }
            />
        </aside>
    );
}
