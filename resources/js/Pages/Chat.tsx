import ChatInterface from '@/Components/Chat/ChatInterface';
import ChatLayout from '@/Layouts/ChatLayout';
import { Head } from '@inertiajs/react';
import { AgentSession, AvailableModels } from '@/types';

interface Props {
    session: AgentSession | null;
    availableModels: AvailableModels;
}

export default function Chat({ session, availableModels }: Props) {
    return (
        <ChatLayout currentSessionId={session?.id}>
            <Head title={session?.title ?? 'New Chat'} />
            <ChatInterface session={session} availableModels={availableModels} />
        </ChatLayout>
    );
}
