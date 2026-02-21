<?php

namespace App\Services\Email;

interface MailboxService
{
    public function connect(): void;

    /**
     * Fetch unseen messages from the inbox.
     *
     * @return array<int, array{uid: string, raw: string}>
     */
    public function fetchUnseen(): array;

    public function markSeen(string $uid): void;

    public function disconnect(): void;
}
