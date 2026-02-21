<?php

use App\Services\Email\JmapService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'channels.email.jmap' => [
            'url' => 'https://mail.example.com',
            'username' => 'testuser',
            'password' => 'testpass',
            'verify_cert' => false,
        ],
    ]);
});

test('connects and discovers session', function () {
    Http::fake([
        'mail.example.com/.well-known/jmap' => Http::response([
            'apiUrl' => 'https://mail.example.com/jmap',
            'accounts' => [
                'acct-001' => ['name' => 'testuser'],
            ],
        ]),
        'mail.example.com/jmap' => Http::response([
            'methodResponses' => [
                ['Mailbox/get', [
                    'list' => [
                        ['id' => 'mbox-inbox', 'name' => 'Inbox', 'role' => 'inbox'],
                        ['id' => 'mbox-sent', 'name' => 'Sent', 'role' => 'sent'],
                    ],
                ], 'mailboxes'],
            ],
        ]),
    ]);

    $service = new JmapService;
    $service->connect();

    Http::assertSentCount(2);
});

test('throws on authentication failure', function () {
    Http::fake([
        'mail.example.com/.well-known/jmap' => Http::response('Unauthorized', 401),
    ]);

    $service = new JmapService;
    $service->connect();
})->throws(RuntimeException::class, 'JMAP authentication failed');

test('throws when no accounts in session', function () {
    Http::fake([
        'mail.example.com/.well-known/jmap' => Http::response([
            'apiUrl' => 'https://mail.example.com/jmap',
            'accounts' => [],
        ]),
    ]);

    $service = new JmapService;
    $service->connect();
})->throws(RuntimeException::class, 'JMAP session has no accounts');

test('throws when inbox mailbox not found', function () {
    Http::fake([
        'mail.example.com/.well-known/jmap' => Http::response([
            'apiUrl' => 'https://mail.example.com/jmap',
            'accounts' => [
                'acct-001' => ['name' => 'testuser'],
            ],
        ]),
        'mail.example.com/jmap' => Http::response([
            'methodResponses' => [
                ['Mailbox/get', [
                    'list' => [
                        ['id' => 'mbox-sent', 'name' => 'Sent', 'role' => 'sent'],
                    ],
                ], 'mailboxes'],
            ],
        ]),
    ]);

    $service = new JmapService;
    $service->connect();
})->throws(RuntimeException::class, 'Inbox mailbox not found');

test('fetches inbox messages', function () {
    Http::fake([
        'mail.example.com/.well-known/jmap' => Http::response([
            'apiUrl' => 'https://mail.example.com/jmap',
            'accounts' => [
                'acct-001' => ['name' => 'testuser'],
            ],
        ]),
        'mail.example.com/jmap' => Http::sequence()
            // First call: Mailbox/get during connect
            ->push([
                'methodResponses' => [
                    ['Mailbox/get', [
                        'list' => [
                            ['id' => 'mbox-inbox', 'name' => 'Inbox', 'role' => 'inbox'],
                        ],
                    ], 'mailboxes'],
                ],
            ])
            // Second call: Email/query during fetchInbox
            ->push([
                'methodResponses' => [
                    ['Email/query', [
                        'ids' => ['email-001', 'email-002'],
                    ], 'query'],
                ],
            ])
            // Third call: Email/get during fetchInbox
            ->push([
                'methodResponses' => [
                    ['Email/get', [
                        'list' => [
                            [
                                'id' => 'email-001',
                                'from' => [['name' => 'Mark', 'email' => 'markc@renta.net']],
                                'to' => [['name' => '', 'email' => 'claw@goldcoast.org']],
                                'subject' => 'Hello',
                                'receivedAt' => '2026-02-21T10:00:00Z',
                                'messageId' => ['abc123@renta.net'],
                                'inReplyTo' => [],
                                'references' => [],
                                'textBody' => [['partId' => 'p1']],
                                'bodyValues' => ['p1' => ['value' => 'Test message body.']],
                            ],
                            [
                                'id' => 'email-002',
                                'from' => [['name' => '', 'email' => 'user@example.com']],
                                'to' => [['name' => '', 'email' => 'claw@goldcoast.org']],
                                'subject' => 'Another',
                                'receivedAt' => '2026-02-21T11:00:00Z',
                                'messageId' => ['def456@example.com'],
                                'inReplyTo' => [],
                                'references' => [],
                                'textBody' => [['partId' => 'p1']],
                                'bodyValues' => ['p1' => ['value' => 'Second message.']],
                            ],
                        ],
                    ], 'fetch'],
                ],
            ]),
    ]);

    $service = new JmapService;
    $service->connect();
    $messages = $service->fetchInbox();

    expect($messages)->toHaveCount(2)
        ->and($messages[0]['uid'])->toBe('email-001')
        ->and($messages[0]['raw'])->toContain('From: Mark <markc@renta.net>')
        ->and($messages[0]['raw'])->toContain('Subject: Hello')
        ->and($messages[0]['raw'])->toContain('Test message body.')
        ->and($messages[1]['uid'])->toBe('email-002')
        ->and($messages[1]['raw'])->toContain('From: user@example.com');
});

test('returns empty array when no inbox messages', function () {
    Http::fake([
        'mail.example.com/.well-known/jmap' => Http::response([
            'apiUrl' => 'https://mail.example.com/jmap',
            'accounts' => [
                'acct-001' => ['name' => 'testuser'],
            ],
        ]),
        'mail.example.com/jmap' => Http::sequence()
            ->push([
                'methodResponses' => [
                    ['Mailbox/get', [
                        'list' => [
                            ['id' => 'mbox-inbox', 'name' => 'Inbox', 'role' => 'inbox'],
                        ],
                    ], 'mailboxes'],
                ],
            ])
            ->push([
                'methodResponses' => [
                    ['Email/query', [
                        'ids' => [],
                    ], 'query'],
                ],
            ]),
    ]);

    $service = new JmapService;
    $service->connect();
    $messages = $service->fetchInbox();

    expect($messages)->toBeEmpty();
});

test('marks message as seen', function () {
    Http::fake([
        'mail.example.com/.well-known/jmap' => Http::response([
            'apiUrl' => 'https://mail.example.com/jmap',
            'accounts' => [
                'acct-001' => ['name' => 'testuser'],
            ],
        ]),
        'mail.example.com/jmap' => Http::sequence()
            ->push([
                'methodResponses' => [
                    ['Mailbox/get', [
                        'list' => [
                            ['id' => 'mbox-inbox', 'name' => 'Inbox', 'role' => 'inbox'],
                        ],
                    ], 'mailboxes'],
                ],
            ])
            ->push([
                'methodResponses' => [
                    ['Email/set', [
                        'updated' => ['email-001' => null],
                    ], 'mark'],
                ],
            ]),
    ]);

    $service = new JmapService;
    $service->connect();
    $service->markSeen('email-001');

    Http::assertSentCount(3);
});

test('disconnect is a no-op', function () {
    $service = new JmapService;
    $service->disconnect();

    expect(true)->toBeTrue();
});

test('builds raw email with reply headers', function () {
    Http::fake([
        'mail.example.com/.well-known/jmap' => Http::response([
            'apiUrl' => 'https://mail.example.com/jmap',
            'accounts' => [
                'acct-001' => ['name' => 'testuser'],
            ],
        ]),
        'mail.example.com/jmap' => Http::sequence()
            ->push([
                'methodResponses' => [
                    ['Mailbox/get', [
                        'list' => [
                            ['id' => 'mbox-inbox', 'name' => 'Inbox', 'role' => 'inbox'],
                        ],
                    ], 'mailboxes'],
                ],
            ])
            ->push([
                'methodResponses' => [
                    ['Email/query', [
                        'ids' => ['email-reply'],
                    ], 'query'],
                ],
            ])
            ->push([
                'methodResponses' => [
                    ['Email/get', [
                        'list' => [
                            [
                                'id' => 'email-reply',
                                'from' => [['name' => 'Mark', 'email' => 'markc@renta.net']],
                                'to' => [['name' => 'Claw', 'email' => 'claw@goldcoast.org']],
                                'subject' => 'Re: Previous topic',
                                'receivedAt' => '2026-02-21T12:00:00Z',
                                'messageId' => ['reply1@renta.net'],
                                'inReplyTo' => ['original@goldcoast.org'],
                                'references' => ['original@goldcoast.org', 'mid2@goldcoast.org'],
                                'textBody' => [['partId' => 'p1']],
                                'bodyValues' => ['p1' => ['value' => 'Follow up message.']],
                            ],
                        ],
                    ], 'fetch'],
                ],
            ]),
    ]);

    $service = new JmapService;
    $service->connect();
    $messages = $service->fetchInbox();

    $raw = $messages[0]['raw'];

    expect($raw)->toContain('In-Reply-To: <original@goldcoast.org>')
        ->and($raw)->toContain('References: original@goldcoast.org mid2@goldcoast.org')
        ->and($raw)->toContain('Message-ID: <reply1@renta.net>')
        ->and($raw)->toContain('Subject: Re: Previous topic')
        ->and($raw)->toContain('Follow up message.');
});
