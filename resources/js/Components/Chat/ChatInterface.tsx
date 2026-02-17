import { useStream } from '@laravel/stream-react';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { AgentMessage, AgentSession, AvailableModels } from '@/types';
import MessageList from './MessageList';
import MessageInput from './MessageInput';

interface Props {
    session: AgentSession | null;
    availableModels: AvailableModels;
}

interface LocalMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    isStreaming?: boolean;
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export default function ChatInterface({ session, availableModels }: Props) {
    const [messages, setMessages] = useState<LocalMessage[]>([]);
    const [sessionKey, setSessionKey] = useState<string | null>(session?.session_key ?? null);
    const [selectedModel, setSelectedModel] = useState<string>(
        session?.model ?? 'claude-sonnet-4-5-20250929'
    );
    const [selectedProvider, setSelectedProvider] = useState<string>(
        session?.provider ?? 'anthropic'
    );
    const [systemPrompt, setSystemPrompt] = useState<string>(session?.system_prompt ?? '');
    const [isStreaming, setIsStreaming] = useState(false);

    const streamRef = useRef<ReturnType<typeof useStream> | null>(null);

    // Load existing messages from session
    useEffect(() => {
        if (session?.messages) {
            setMessages(
                session.messages.map((m) => ({
                    id: String(m.id),
                    role: m.role as 'user' | 'assistant',
                    content: m.content,
                }))
            );
        } else {
            setMessages([]);
        }
        setSessionKey(session?.session_key ?? null);
    }, [session?.id]);

    const handleSend = useCallback(
        async (content: string) => {
            if (isStreaming || !content.trim()) return;

            // Add user message to local state
            const userMsg: LocalMessage = {
                id: `local-${Date.now()}`,
                role: 'user',
                content: content.trim(),
            };
            setMessages((prev) => [...prev, userMsg]);
            setIsStreaming(true);

            // Add placeholder for assistant response
            const assistantId = `stream-${Date.now()}`;
            setMessages((prev) => [
                ...prev,
                { id: assistantId, role: 'assistant', content: '', isStreaming: true },
            ]);

            try {
                const response = await fetch(route('chat.stream'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': getCsrfToken(),
                        Accept: 'text/event-stream',
                    },
                    body: JSON.stringify({
                        message: content.trim(),
                        session_key: sessionKey,
                        model: selectedModel,
                        provider: selectedProvider,
                        system_prompt: systemPrompt || undefined,
                    }),
                });

                // Get session key from response headers
                const newSessionKey = response.headers.get('X-Session-Key');
                if (newSessionKey && !sessionKey) {
                    setSessionKey(newSessionKey);
                }

                const reader = response.body?.getReader();
                if (!reader) return;

                const decoder = new TextDecoder();
                let accumulated = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    const chunk = decoder.decode(value, { stream: true });
                    const lines = chunk.split('\n');

                    for (const line of lines) {
                        if (line.startsWith('0:')) {
                            // Vercel AI protocol: text delta
                            try {
                                const text = JSON.parse(line.slice(2));
                                accumulated += text;
                                setMessages((prev) =>
                                    prev.map((m) =>
                                        m.id === assistantId
                                            ? { ...m, content: accumulated }
                                            : m
                                    )
                                );
                            } catch {
                                // Ignore parse errors
                            }
                        } else if (line.startsWith('d:')) {
                            // Vercel AI protocol: done
                            break;
                        }
                    }
                }

                // Mark streaming complete
                setMessages((prev) =>
                    prev.map((m) =>
                        m.id === assistantId ? { ...m, isStreaming: false } : m
                    )
                );

                // Reload sidebar to show new conversation
                router.reload({ only: ['sidebarConversations', 'sidebarStats'] });
            } catch (error) {
                console.error('Stream error:', error);
                setMessages((prev) =>
                    prev.map((m) =>
                        m.id === assistantId
                            ? { ...m, content: 'Error: Failed to get response.', isStreaming: false }
                            : m
                    )
                );
            } finally {
                setIsStreaming(false);
            }
        },
        [isStreaming, sessionKey, selectedModel, selectedProvider, systemPrompt]
    );

    return (
        <div className="flex h-full flex-col">
            <MessageList messages={messages} />
            <MessageInput
                onSend={handleSend}
                isStreaming={isStreaming}
                selectedModel={selectedModel}
                selectedProvider={selectedProvider}
                onModelChange={(model, provider) => {
                    setSelectedModel(model);
                    setSelectedProvider(provider);
                }}
                availableModels={availableModels}
                systemPrompt={systemPrompt}
                onSystemPromptChange={setSystemPrompt}
            />
        </div>
    );
}
