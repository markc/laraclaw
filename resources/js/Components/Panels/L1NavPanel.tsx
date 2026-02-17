import { Link, usePage } from '@inertiajs/react';
import { Home, MessageSquare, Settings, User } from 'lucide-react';

const navItems = [
    { path: '/chat', label: 'Chat', icon: MessageSquare },
    { path: '/dashboard', label: 'Dashboard', icon: Home },
    { path: '/profile', label: 'Profile', icon: User },
];

export default function L1NavPanel() {
    const { url: pageUrl } = usePage();

    return (
        <nav className="flex flex-col py-2">
            {navItems.map(item => {
                const isActive = pageUrl.startsWith(item.path);
                return (
                    <Link
                        key={item.path}
                        href={item.path}
                        className={`flex items-center gap-3 border-l-[3px] px-3 py-2 text-sm transition-colors ${
                            isActive
                                ? 'border-[var(--scheme-accent)] bg-background text-[var(--scheme-accent)]'
                                : 'border-transparent hover:border-muted-foreground hover:bg-background'
                        }`}
                        style={{ color: isActive ? 'var(--scheme-accent)' : undefined }}
                    >
                        <item.icon className="h-4 w-4" />
                        {item.label}
                    </Link>
                );
            })}

            <div className="mx-3 my-2 border-t" style={{ borderColor: 'var(--glass-border)' }} />

            <Link
                href={route('logout')}
                method="post"
                as="button"
                className="flex items-center gap-3 border-l-[3px] border-transparent px-3 py-2 text-sm transition-colors hover:border-muted-foreground hover:bg-background"
            >
                <Settings className="h-4 w-4" />
                Logout
            </Link>
        </nav>
    );
}
