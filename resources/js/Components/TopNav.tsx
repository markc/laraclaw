export default function TopNav() {
    return (
        <header
            className="flex h-[var(--topnav-height)] items-center justify-center border-b"
            style={{
                background: 'var(--glass)',
                backdropFilter: 'blur(20px)',
                WebkitBackdropFilter: 'blur(20px)',
                borderColor: 'var(--glass-border)',
            }}
        >
            <h1 className="text-xl font-bold tracking-tight" style={{ color: 'var(--scheme-accent)' }}>
                LaRaClaw
            </h1>
        </header>
    );
}
