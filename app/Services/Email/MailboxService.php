<?php

namespace App\Services\Email;

interface MailboxService
{
    public function connect(): void;

    /**
     * Fetch recent inbox messages (regardless of seen/unseen state).
     *
     * @return array<int, array{uid: string, raw: string}>
     */
    public function fetchInbox(): array;

    public function markSeen(string $uid): void;

    public function disconnect(): void;
}
