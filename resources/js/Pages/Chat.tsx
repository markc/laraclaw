import { Head, usePage } from '@inertiajs/react';
import { useLayoutEffect } from 'react';
import ChatInterface from '@/Components/Chat/ChatInterface';
import ChatLayout from '@/Layouts/ChatLayout';
import { useTheme } from '@/Contexts/ThemeContext';
import { AgentSession, AvailableModels, PageProps } from '@/types';

interface Props {
    session: AgentSession | null;
    availableModels: AvailableModels;
}

function ChatPage({ session, availableModels }: Props) {
    const { setNoPadding, setPanel } = useTheme();
    const title = session?.title ?? 'New Chat';

    useLayoutEffect(() => {
        setNoPadding(true);
        setPanel('left', 1);
        return () => setNoPadding(false);
    }, [setNoPadding, setPanel]);

    return (
        <>
            <Head title={title} />
            <ChatInterface session={session} availableModels={availableModels} />
        </>
    );
}

export default function Chat() {
    const { session, availableModels } = usePage<PageProps & Props>().props;

    return (
        <ChatLayout>
            <ChatPage session={session} availableModels={availableModels} />
        </ChatLayout>
    );
}
