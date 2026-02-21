<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class JmapService implements MailboxService
{
    protected string $apiUrl;

    protected string $accountId;

    protected string $username;

    protected string $password;

    protected string $inboxId;

    public function connect(): void
    {
        $config = config('channels.email.jmap');

        $this->username = $config['username'];
        $this->password = $config['password'];

        $sessionUrl = rtrim($config['url'], '/').'/.well-known/jmap';

        $response = Http::withBasicAuth($this->username, $this->password)
            ->withOptions(['verify' => $config['verify_cert'] ?? true])
            ->get($sessionUrl);

        if ($response->status() === 401 || $response->status() === 403) {
            throw new RuntimeException('JMAP authentication failed: '.$response->body());
        }

        if (! $response->successful()) {
            throw new RuntimeException('JMAP session discovery failed: HTTP '.$response->status());
        }

        $session = $response->json();
        $this->apiUrl = $session['apiUrl'];

        $accountId = array_key_first($session['accounts'] ?? []);
        if (! $accountId) {
            throw new RuntimeException('JMAP session has no accounts');
        }
        $this->accountId = $accountId;

        $this->inboxId = $this->findInboxId();
    }

    public function fetchInbox(): array
    {
        $since = now()->subHours(24)->toIso8601String();

        $response = $this->call([
            ['Email/query', [
                'accountId' => $this->accountId,
                'filter' => [
                    'inMailbox' => $this->inboxId,
                    'after' => $since,
                ],
                'sort' => [['property' => 'receivedAt', 'isAscending' => false]],
                'limit' => 50,
            ], 'query'],
        ]);

        $queryResult = $this->findResult($response, 'Email/query', 'query');
        $emailIds = $queryResult['ids'] ?? [];

        if (empty($emailIds)) {
            return [];
        }

        $response = $this->call([
            ['Email/get', [
                'accountId' => $this->accountId,
                'ids' => $emailIds,
                'properties' => ['id', 'blobId', 'from', 'to', 'subject', 'receivedAt',
                    'messageId', 'inReplyTo', 'references', 'bodyValues', 'textBody'],
                'fetchTextBodyValues' => true,
            ], 'fetch'],
        ]);

        $fetchResult = $this->findResult($response, 'Email/get', 'fetch');
        $emails = $fetchResult['list'] ?? [];

        return array_map(fn (array $email) => [
            'uid' => $email['id'],
            'raw' => $this->buildRawFromJmap($email),
        ], $emails);
    }

    public function markSeen(string $uid): void
    {
        $this->call([
            ['Email/set', [
                'accountId' => $this->accountId,
                'update' => [
                    $uid => ['keywords/$seen' => true],
                ],
            ], 'mark'],
        ]);
    }

    public function disconnect(): void
    {
        // JMAP is stateless HTTP — nothing to disconnect
    }

    protected function findInboxId(): string
    {
        $response = $this->call([
            ['Mailbox/get', [
                'accountId' => $this->accountId,
            ], 'mailboxes'],
        ]);

        $result = $this->findResult($response, 'Mailbox/get', 'mailboxes');
        $mailboxes = $result['list'] ?? [];

        foreach ($mailboxes as $mailbox) {
            if (($mailbox['role'] ?? '') === 'inbox') {
                return $mailbox['id'];
            }
        }

        throw new RuntimeException('JMAP: Inbox mailbox not found');
    }

    /**
     * @param  array<int, array{0: string, 1: array, 2: string}>  $methodCalls
     */
    protected function call(array $methodCalls): array
    {
        $body = json_encode([
            'using' => [
                'urn:ietf:params:jmap:core',
                'urn:ietf:params:jmap:mail',
            ],
            'methodCalls' => $methodCalls,
        ], JSON_UNESCAPED_SLASHES);

        $response = Http::withBasicAuth($this->username, $this->password)
            ->withOptions(['verify' => config('channels.email.jmap.verify_cert', true)])
            ->withBody($body, 'application/json')
            ->post($this->apiUrl);

        if (! $response->successful()) {
            throw new RuntimeException('JMAP request failed: HTTP '.$response->status());
        }

        return $response->json();
    }

    protected function findResult(array $response, string $method, string $callId): array
    {
        foreach ($response['methodResponses'] ?? [] as $methodResponse) {
            if ($methodResponse[0] === $method && $methodResponse[2] === $callId) {
                return $methodResponse[1];
            }
        }

        throw new RuntimeException("JMAP: {$method} response not found");
    }

    /**
     * Build a pseudo-raw email from JMAP structured data.
     * This allows the existing EmailParserService to process it.
     */
    protected function buildRawFromJmap(array $email): string
    {
        $from = $this->formatAddress($email['from'][0] ?? []);
        $to = $this->formatAddress($email['to'][0] ?? []);
        $subject = $email['subject'] ?? '';
        $date = $email['receivedAt'] ?? gmdate('r');
        $messageId = $email['messageId'][0] ?? '';
        $inReplyTo = $email['inReplyTo'][0] ?? '';
        $references = implode(' ', $email['references'] ?? []);

        $body = '';
        foreach ($email['textBody'] ?? [] as $part) {
            $partId = $part['partId'] ?? '';
            $body .= $email['bodyValues'][$partId]['value'] ?? '';
        }

        $headers = "From: {$from}\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "Date: {$date}\r\n";

        if ($messageId) {
            $headers .= "Message-ID: <{$messageId}>\r\n";
        }
        if ($inReplyTo) {
            $headers .= "In-Reply-To: <{$inReplyTo}>\r\n";
        }
        if ($references) {
            $headers .= "References: {$references}\r\n";
        }

        $headers .= "Content-Type: text/plain; charset=utf-8\r\n";

        return $headers."\r\n".$body;
    }

    protected function formatAddress(array $addr): string
    {
        $email = $addr['email'] ?? '';
        $name = $addr['name'] ?? '';

        return $name ? "{$name} <{$email}>" : $email;
    }
}
