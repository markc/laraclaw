import { FormEvent, KeyboardEvent, useRef, useState } from 'react';
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
        <div className="border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
            <div className="mx-auto max-w-3xl">
                {/* Model selector and system prompt toggle */}
                <div className="mb-2 flex items-center gap-3">
                    <select
                        value={`${selectedProvider}:${selectedModel}`}
                        onChange={(e) => {
                            const [provider, ...modelParts] = e.target.value.split(':');
                            onModelChange(modelParts.join(':'), provider);
                        }}
                        className="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
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
                        className={`text-xs ${
                            showSystemPrompt || systemPrompt
                                ? 'text-indigo-600 dark:text-indigo-400'
                                : 'text-gray-400 hover:text-gray-600'
                        }`}
                    >
                        System Prompt {systemPrompt ? '●' : ''}
                    </button>
                </div>

                {/* System prompt editor */}
                {showSystemPrompt && (
                    <div className="mb-2">
                        <textarea
                            value={systemPrompt}
                            onChange={(e) => onSystemPromptChange(e.target.value)}
                            placeholder="Custom system prompt (optional)..."
                            rows={3}
                            className="w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-xs text-gray-700 placeholder-gray-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                        />
                    </div>
                )}

                {/* Message input */}
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
                        className="flex-1 resize-none rounded-xl border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                    <button
                        type="submit"
                        disabled={isStreaming || !input.trim()}
                        className="rounded-xl bg-indigo-600 px-4 py-3 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {isStreaming ? (
                            <svg className="h-5 w-5 animate-spin" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                        ) : (
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                        )}
                    </button>
                </form>
            </div>
        </div>
    );
}
