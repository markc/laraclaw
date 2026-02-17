import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';

type PanelDef = {
    label: string;
    content: ReactNode;
};

type PanelCarouselProps = {
    panels: PanelDef[];
    activePanel: number;
    onPanelChange: (index: number) => void;
    side: 'left' | 'right';
    headerSlot?: ReactNode;
};

export default function PanelCarousel({ panels, activePanel, onPanelChange, side, headerSlot }: PanelCarouselProps) {
    const len = panels.length;
    const prev = (activePanel - 1 + len) % len;
    const next = (activePanel + 1) % len;

    const carouselNav = (
        <div className="flex items-center gap-1.5">
            <button
                onClick={() => onPanelChange(prev)}
                className="rounded p-0.5 transition-colors hover:bg-background"
                style={{ color: 'var(--scheme-fg-muted)', fontSize: 22 }}
                aria-label="Previous panel"
            >
                <ChevronLeft className="h-[1em] w-[1em]" />
            </button>
            <div className="flex items-center gap-1.5">
                {panels.map((p, i) => (
                    <button
                        key={p.label}
                        onClick={() => onPanelChange(i)}
                        className="transition-all"
                        style={{
                            width: i === activePanel ? 24 : 9,
                            height: 9,
                            borderRadius: 5,
                            backgroundColor: i === activePanel ? 'var(--scheme-accent)' : 'var(--scheme-fg-muted)',
                            opacity: i === activePanel ? 1 : 0.4,
                        }}
                        aria-label={p.label}
                    />
                ))}
            </div>
            <button
                onClick={() => onPanelChange(next)}
                className="rounded p-0.5 transition-colors hover:bg-background"
                style={{ color: 'var(--scheme-fg-muted)', fontSize: 22 }}
                aria-label="Next panel"
            >
                <ChevronRight className="h-[1em] w-[1em]" />
            </button>
        </div>
    );

    return (
        <>
            <div
                className={`flex h-[var(--topnav-height)] shrink-0 items-center border-b gap-1 ${
                    side === 'left' ? 'justify-start pl-[3.75rem]' : 'justify-end pr-[3.75rem]'
                }`}
                style={{ borderColor: 'var(--glass-border)' }}
            >
                {side === 'left' && headerSlot}
                {carouselNav}
                {side === 'right' && headerSlot}
            </div>

            <div className="relative flex-1 overflow-hidden">
                <div
                    className="flex h-full transition-transform duration-300 ease-in-out"
                    style={{ transform: `translateX(-${activePanel * 100}%)` }}
                >
                    {panels.map((p) => {
                        const displayName = p.label.replace(/^[LR]\d+:\s*/, '');
                        return (
                            <div key={p.label} className="flex h-full w-full shrink-0 flex-col">
                                <div
                                    className="shrink-0 border-b px-4 py-2 text-center"
                                    style={{ borderColor: 'var(--glass-border)', background: 'color-mix(in oklch, var(--scheme-accent) 4%, transparent)' }}
                                    title={p.label}
                                >
                                    <h2 className="text-sm font-bold" style={{ color: 'var(--scheme-fg-primary)' }}>
                                        {displayName}
                                    </h2>
                                </div>
                                <div className="flex-1 overflow-y-auto">
                                    {p.content}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </>
    );
}
