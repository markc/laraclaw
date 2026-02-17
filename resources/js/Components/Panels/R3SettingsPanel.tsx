import { Link, usePage } from '@inertiajs/react';
import { LogOut, User } from 'lucide-react';
import type { PageProps } from '@/types';

export default function R3SettingsPanel() {
    const { auth } = usePage<PageProps>().props;

    return (
        <div className="flex flex-col gap-4 p-4">
            <div className="rounded-lg border p-3" style={{ borderColor: 'var(--glass-border)', background: 'var(--glass)' }}>
                <div className="flex items-center gap-2">
                    <User className="h-3.5 w-3.5" style={{ color: 'var(--scheme-accent)' }} />
                    <span className="text-xs" style={{ color: 'var(--scheme-fg-muted)' }}>Account</span>
                </div>
                <div className="mt-1 text-sm font-medium" style={{ color: 'var(--scheme-fg-primary)' }}>
                    {auth.user.name}
                </div>
                <div className="mt-0.5 text-xs" style={{ color: 'var(--scheme-fg-muted)' }}>
                    {auth.user.email}
                </div>
            </div>

            <Link
                href={route('profile.edit')}
                className="rounded-lg border px-3 py-2 text-center text-sm transition-colors hover:bg-background"
                style={{ borderColor: 'var(--glass-border)', color: 'var(--scheme-fg-secondary)' }}
            >
                Edit Profile
            </Link>

            <Link
                href={route('logout')}
                method="post"
                as="button"
                className="flex items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors hover:bg-background"
                style={{ borderColor: 'var(--glass-border)', color: 'var(--scheme-fg-muted)' }}
            >
                <LogOut className="h-3.5 w-3.5" />
                Sign Out
            </Link>
        </div>
    );
}
