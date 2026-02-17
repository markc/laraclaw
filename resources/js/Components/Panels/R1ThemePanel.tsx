import { useTheme, type ColorScheme } from '@/Contexts/ThemeContext';

const schemes: { id: ColorScheme; label: string; hue: string }[] = [
    { id: 'crimson', label: 'Crimson', hue: 'oklch(47% 0.2 25)' },
    { id: 'stone', label: 'Stone', hue: 'oklch(45% 0.05 60)' },
    { id: 'ocean', label: 'Ocean', hue: 'oklch(50% 0.15 220)' },
    { id: 'forest', label: 'Forest', hue: 'oklch(50% 0.12 150)' },
    { id: 'sunset', label: 'Sunset', hue: 'oklch(60% 0.16 45)' },
];

export default function R1ThemePanel() {
    const { scheme, theme, sidebarWidth, setScheme, toggleTheme, setSidebarWidth } = useTheme();

    return (
        <div className="flex flex-col gap-4 p-4">
            <div className="flex gap-2">
                <button
                    onClick={() => { if (theme !== 'light') toggleTheme(); }}
                    className={`flex-1 rounded-lg px-3 py-2 text-sm transition-colors ${
                        theme === 'light' ? 'bg-background font-medium' : 'hover:bg-background'
                    }`}
                    style={{ color: theme === 'light' ? 'var(--scheme-accent)' : 'var(--scheme-fg-secondary)' }}
                >
                    Light
                </button>
                <button
                    onClick={() => { if (theme !== 'dark') toggleTheme(); }}
                    className={`flex-1 rounded-lg px-3 py-2 text-sm transition-colors ${
                        theme === 'dark' ? 'bg-background font-medium' : 'hover:bg-background'
                    }`}
                    style={{ color: theme === 'dark' ? 'var(--scheme-accent)' : 'var(--scheme-fg-secondary)' }}
                >
                    Dark
                </button>
            </div>

            <div className="flex flex-col gap-1">
                <label className="text-xs" style={{ color: 'var(--scheme-fg-muted)' }}>
                    Sidebar width: {sidebarWidth}px
                </label>
                <input
                    type="range"
                    min={200}
                    max={500}
                    step={10}
                    value={sidebarWidth}
                    onChange={(e) => setSidebarWidth(Number(e.target.value))}
                    className="w-full accent-[var(--scheme-accent)]"
                />
            </div>

            <div className="flex flex-col gap-1.5">
                {schemes.map(s => (
                    <button
                        key={s.id}
                        onClick={() => setScheme(s.id)}
                        className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors ${
                            scheme === s.id ? 'ring-2' : 'hover:bg-background'
                        }`}
                        style={{
                            outlineColor: scheme === s.id ? s.hue : undefined,
                            outlineWidth: scheme === s.id ? 2 : undefined,
                            outlineStyle: scheme === s.id ? 'solid' : undefined,
                            outlineOffset: 2,
                            color: scheme === s.id ? s.hue : 'var(--scheme-fg-secondary)',
                        }}
                    >
                        <span
                            className="h-4 w-4 rounded-full"
                            style={{ backgroundColor: s.hue }}
                        />
                        {s.label}
                    </button>
                ))}
            </div>
        </div>
    );
}
