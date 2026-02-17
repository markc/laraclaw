export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export interface AgentMessage {
    id: number;
    session_id: number;
    role: 'user' | 'assistant' | 'system' | 'tool';
    content: string;
    attachments: unknown[];
    tool_calls: unknown[];
    tool_results: unknown[];
    usage: { input_tokens?: number; output_tokens?: number };
    meta: Record<string, unknown>;
    created_at: string;
    updated_at: string;
}

export interface AgentSession {
    id: number;
    session_key: string;
    title: string;
    channel: string;
    model: string | null;
    provider: string | null;
    system_prompt: string | null;
    last_activity_at: string | null;
    updated_at: string;
    messages?: AgentMessage[];
}

export interface ModelOption {
    id: string;
    name: string;
    provider: string;
}

export type AvailableModels = Record<string, ModelOption[]>;

export interface SidebarStats {
    conversations: number;
    messages: number;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    sidebarConversations: AgentSession[];
    sidebarStats: SidebarStats | null;
    availableModels: AvailableModels;
};

// WebSocket stream events (from Laravel AI SDK StreamEvent.toArray())
export interface StreamStartEvent {
    id: string;
    invocation_id: string;
    type: 'stream_start';
    provider: string;
    model: string;
    timestamp: number;
    metadata: Record<string, unknown>;
}

export interface TextDeltaEvent {
    id: string;
    invocation_id: string;
    type: 'text_delta';
    message_id: string;
    delta: string;
    timestamp: number;
}

export interface StreamEndEvent {
    id: string;
    invocation_id: string;
    type: 'stream_end';
    reason: string;
    usage: { input_tokens: number; output_tokens: number };
    timestamp: number;
}

export interface StreamErrorEvent {
    id: string;
    invocation_id: string;
    type: 'error';
    error: string;
    timestamp: number;
}

// Session lifecycle events (broadcast on user channel)
export interface SessionCreatedEvent {
    id: number;
    session_key: string;
    title: string;
    channel: string;
    model: string | null;
    provider: string | null;
    last_activity_at: string | null;
    updated_at: string;
}

export interface SessionUpdatedEvent {
    id: number;
    session_key: string;
    title: string;
    last_activity_at: string | null;
    updated_at: string;
}

export interface SessionDeletedEvent {
    id: number;
}
