<?php

namespace App\Services\Email;

use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

class ImapService implements MailboxService
{
    protected ?Client $client = null;

    public function connect(): void
    {
        $config = config('channels.email.imap');

        $manager = new ClientManager;
        $this->client = $manager->make([
            'host' => $config['host'],
            'port' => $config['port'],
            'encryption' => $config['encryption'],
            'validate_cert' => $config['validate_cert'],
            'username' => $config['username'],
            'password' => $config['password'],
            'protocol' => 'imap',
        ]);

        $this->client->connect();
    }

    /**
     * Fetch recent inbox messages (last 24 hours, regardless of seen state).
     *
     * @return array<int, array{uid: int, raw: string}>
     */
    public function fetchInbox(): array
    {
        $folder = $this->client->getFolder(config('channels.email.imap.folder', 'INBOX'));
        $messages = $folder->query()->since(now()->subDay())->get();

        $results = [];
        foreach ($messages as $message) {
            $results[] = [
                'uid' => $message->getUid(),
                'raw' => $message->getRawBody(),
            ];
        }

        return $results;
    }

    public function markSeen(string $uid): void
    {
        $folder = $this->client->getFolder(config('channels.email.imap.folder', 'INBOX'));
        $message = $folder->query()->getMessageByUid($uid);
        $message?->setFlag('Seen');
    }

    public function disconnect(): void
    {
        $this->client?->disconnect();
        $this->client = null;
    }
}
