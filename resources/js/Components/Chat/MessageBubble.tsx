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
                className={`max-w-[80%] rounded-2xl px-4 py-3 ${
                    isUser
                        ? 'bg-indigo-600 text-white'
                        : 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-100'
                }`}
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
