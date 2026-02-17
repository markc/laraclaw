import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { AgentSession, AvailableModels, TextDeltaEvent, StreamEndEvent, StreamErrorEvent } from '@/types';
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

function generateSessionKey(userId: number): string {
    // Generate a uuid7-like key (timestamp-based for ordering)
    const ts = Date.now().toString(36);
    const rand = Math.random().toString(36).substring(2, 10);
    return `web:${userId}:${ts}-${rand}`;
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

    // Track the accumulated text for the current streaming message
    const accumulatedRef = useRef('');
    const assistantIdRef = useRef<string | null>(null);
    const subscribedChannelRef = useRef<string | null>(null);

    // Load existing messages when session changes
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

    // Subscribe to session channel when sessionKey is set
    useEffect(() => {
        if (!sessionKey) return;

        // Don't re-subscribe to the same channel
        if (subscribedChannelRef.current === sessionKey) return;

        // Leave previous channel if any
        if (subscribedChannelRef.current) {
            window.Echo.leave(`chat.session.${subscribedChannelRef.current}`);
        }

        const channel = window.Echo.private(`chat.session.${sessionKey}`);

        channel.listen('.stream_start', () => {
            const id = `stream-${Date.now()}`;
            assistantIdRef.current = id;
            accumulatedRef.current = '';
            setIsStreaming(true);
            setMessages((prev) => [
                ...prev,
                { id, role: 'assistant', content: '', isStreaming: true },
            ]);
        });

        channel.listen('.text_delta', (e: TextDeltaEvent) => {
            accumulatedRef.current += e.delta;
            const text = accumulatedRef.current;
            const id = assistantIdRef.current;
            if (id) {
                setMessages((prev) =>
                    prev.map((m) => (m.id === id ? { ...m, content: text } : m))
                );
            }
        });

        channel.listen('.stream_end', (_e: StreamEndEvent) => {
            const id = assistantIdRef.current;
            if (id) {
                setMessages((prev) =>
                    prev.map((m) => (m.id === id ? { ...m, isStreaming: false } : m))
                );
            }
            assistantIdRef.current = null;
            setIsStreaming(false);
        });

        channel.listen('.error', (e: StreamErrorEvent) => {
            const id = assistantIdRef.current;
            if (id) {
                setMessages((prev) =>
                    prev.map((m) =>
                        m.id === id
                            ? { ...m, content: `Error: ${e.error}`, isStreaming: false }
                            : m
                    )
                );
            } else {
                // Error without a streaming message — append as new message
                setMessages((prev) => [
                    ...prev,
                    {
                        id: `error-${Date.now()}`,
                        role: 'assistant',
                        content: `Error: ${e.error}`,
                    },
                ]);
            }
            assistantIdRef.current = null;
            setIsStreaming(false);
        });

        subscribedChannelRef.current = sessionKey;

        return () => {
            window.Echo.leave(`chat.session.${sessionKey}`);
            subscribedChannelRef.current = null;
        };
    }, [sessionKey]);

    const handleSend = useCallback(
        async (content: string) => {
            if (isStreaming || !content.trim()) return;

            // Add user message to UI immediately
            const userMsg: LocalMessage = {
                id: `local-${Date.now()}`,
                role: 'user',
                content: content.trim(),
            };
            setMessages((prev) => [...prev, userMsg]);

            // For new chats, generate session key and subscribe before sending
            let currentKey = sessionKey;
            if (!currentKey) {
                // Get userId from the page props
                const userId = (window as any).__page?.props?.auth?.user?.id;
                currentKey = generateSessionKey(userId ?? 0);
                setSessionKey(currentKey);
            }

            try {
                const response = await fetch(route('chat.send'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': getCsrfToken(),
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        message: content.trim(),
                        session_key: currentKey,
                        model: selectedModel,
                        provider: selectedProvider,
                        system_prompt: systemPrompt || undefined,
                    }),
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();
                // Update session key if server returned a different one
                if (data.session_key && data.session_key !== currentKey) {
                    setSessionKey(data.session_key);
                }
            } catch (error) {
                console.error('Send error:', error);
                setMessages((prev) => [
                    ...prev,
                    {
                        id: `error-${Date.now()}`,
                        role: 'assistant',
                        content: 'Error: Failed to send message.',
                    },
                ]);
            }
        },
        [isStreaming, sessionKey, selectedModel, selectedProvider, systemPrompt]
    );

    return (
        <div className="flex flex-col" style={{ height: 'calc(100vh - var(--topnav-height))' }}>
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
