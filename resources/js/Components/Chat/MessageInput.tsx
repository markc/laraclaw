import { FormEvent, KeyboardEvent, useRef, useState } from 'react';
import { Send } from 'lucide-react';
import { AvailableModels } from '@/types';

interface Props {
    onSend: (content: string) => void;
    isStreaming: boolean;
    selectedModel: string;
    selectedProvider: string;
    onModelChange: (model: string, provider: string) => void;
    availableModels: AvailableModels;
    systemPrompt: string;
    onSystemPromptChange: (prompt: string) => void;
}

export default function MessageInput({
    onSend,
    isStreaming,
    selectedModel,
    selectedProvider,
    onModelChange,
    availableModels,
    systemPrompt,
    onSystemPromptChange,
}: Props) {
    const [input, setInput] = useState('');
    const [showSystemPrompt, setShowSystemPrompt] = useState(false);
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!input.trim() || isStreaming) return;
        onSend(input);
        setInput('');
        if (textareaRef.current) {
            textareaRef.current.style.height = 'auto';
        }
    };

    const handleKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSubmit(e);
        }
    };

    const handleInput = () => {
        const textarea = textareaRef.current;
        if (textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 200) + 'px';
        }
    };

    return (
        <div
            className="border-t px-4 py-3"
            style={{ borderColor: 'var(--glass-border)', background: 'var(--glass)' }}
        >
            <div className="mx-auto max-w-3xl">
                <div className="mb-2 flex items-center gap-3">
                    <select
                        value={`${selectedProvider}:${selectedModel}`}
                        onChange={(e) => {
                            const [provider, ...modelParts] = e.target.value.split(':');
                            onModelChange(modelParts.join(':'), provider);
                        }}
                        className="rounded-md border bg-transparent px-2 py-1 text-xs outline-none"
                        style={{ borderColor: 'var(--scheme-border)', color: 'var(--scheme-fg-secondary)' }}
                    >
                        {Object.entries(availableModels).map(([provider, models]) => (
                            <optgroup key={provider} label={provider.charAt(0).toUpperCase() + provider.slice(1)}>
                                {models.map((m) => (
                                    <option key={m.id} value={`${m.provider}:${m.id}`}>
                                        {m.name}
                                    </option>
                                ))}
                            </optgroup>
                        ))}
                        {Object.keys(availableModels).length === 0 && (
                            <option disabled>No API keys configured</option>
                        )}
                    </select>

                    <button
                        type="button"
                        onClick={() => setShowSystemPrompt(!showSystemPrompt)}
                        className="text-xs transition-colors"
                        style={{
                            color: showSystemPrompt || systemPrompt
                                ? 'var(--scheme-accent)'
                                : 'var(--scheme-fg-muted)',
                        }}
                    >
                        System Prompt {systemPrompt ? '●' : ''}
                    </button>
                </div>

                {showSystemPrompt && (
                    <div className="mb-2">
                        <textarea
                            value={systemPrompt}
                            onChange={(e) => onSystemPromptChange(e.target.value)}
                            placeholder="Custom system prompt (optional)..."
                            rows={3}
                            className="w-full rounded-md border bg-transparent px-3 py-2 text-xs outline-none placeholder:text-muted-foreground focus:border-[var(--scheme-accent)]"
                            style={{ borderColor: 'var(--scheme-border)', color: 'var(--scheme-fg-secondary)' }}
                        />
                    </div>
                )}

                <form onSubmit={handleSubmit} className="flex items-end gap-2">
                    <textarea
                        ref={textareaRef}
                        value={input}
                        onChange={(e) => setInput(e.target.value)}
                        onKeyDown={handleKeyDown}
                        onInput={handleInput}
                        placeholder="Type a message..."
                        rows={1}
                        disabled={isStreaming}
                        className="flex-1 resize-none rounded-xl border bg-transparent px-4 py-3 text-sm outline-none placeholder:text-muted-foreground focus:border-[var(--scheme-accent)] disabled:opacity-50"
                        style={{ borderColor: 'var(--scheme-border)', color: 'var(--scheme-fg-primary)' }}
                    />
                    <button
                        type="submit"
                        disabled={isStreaming || !input.trim()}
                        className="rounded-xl px-4 py-3 text-sm font-medium transition-colors disabled:opacity-50"
                        style={{ backgroundColor: 'var(--scheme-accent)', color: 'var(--scheme-accent-fg)' }}
                    >
                        {isStreaming ? (
                            <svg className="h-5 w-5 animate-spin" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                        ) : (
                            <Send className="h-5 w-5" />
                        )}
                    </button>
                </form>
            </div>
        </div>
    );
}
