interface LocalMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    isStreaming?: boolean;
}

export default function MessageBubble({ message }: { message: LocalMessage }) {
    const isUser = message.role === 'user';

    return (
        <div className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}>
            <div
                className="max-w-[80%] rounded-2xl px-4 py-3"
                style={
                    isUser
                        ? { backgroundColor: 'var(--scheme-accent)', color: 'var(--scheme-accent-fg)' }
                        : { backgroundColor: 'var(--scheme-bg-secondary)', color: 'var(--scheme-fg-primary)' }
                }
            >
                <div className="whitespace-pre-wrap text-sm leading-relaxed">
                    {message.content}
                    {message.isStreaming && !message.content && (
                        <span className="inline-flex gap-1">
                            <span className="animate-pulse">●</span>
                            <span className="animate-pulse" style={{ animationDelay: '0.2s' }}>●</span>
                            <span className="animate-pulse" style={{ animationDelay: '0.4s' }}>●</span>
                        </span>
                    )}
                    {message.isStreaming && message.content && (
                        <span className="ml-1 inline-block h-4 w-1 animate-pulse bg-current" />
                    )}
                </div>
            </div>
        </div>
    );
}
