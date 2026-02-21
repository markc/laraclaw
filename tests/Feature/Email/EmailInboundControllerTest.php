<?php

use App\Jobs\ProcessEmailMessage;
use App\Models\AgentSession;
use App\Models\EmailThread;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['channels.email.enabled' => true]);
    config(['channels.email.allow_from' => ['markc@renta.net']]);
    config(['channels.email.address' => 'claw@kanary.org']);
    config(['channels.email.mta_hook.secret' => 'test-secret-123']);
});

function mtaHookPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'context' => [
            'stage' => 'DATA',
            'server' => ['name' => 'Stalwart', 'port' => 25, 'ip' => '127.0.0.1'],
        ],
        'envelope' => [
            'from' => ['address' => 'markc@renta.net', 'parameters' => null],
            'to' => [['address' => 'claw@kanary.org', 'parameters' => null]],
        ],
        'message' => [
            'headers' => [
                ['From', 'Mark <markc@renta.net>'],
                ['To', 'Claw <claw@kanary.org>'],
                ['Subject', 'Hello agent'],
                ['Message-ID', '<hook-test-1@renta.net>'],
                ['Date', 'Fri, 21 Feb 2026 10:00:00 +1000'],
            ],
            'serverHeaders' => [
                ['Received', 'from mail.renta.net by claw.goldcoast.org (Stalwart) with ESMTPS'],
            ],
            'contents' => "What is the weather today?\r\n",
            'size' => 250,
        ],
    ], $overrides);
}

test('rejects missing secret header with 401', function () {
    $response = $this->postJson('/api/email/inbound', mtaHookPayload());

    $response->assertStatus(401)
        ->assertJson(['error' => 'Unauthorized']);
});

test('rejects invalid secret header with 401', function () {
    $response = $this->postJson('/api/email/inbound', mtaHookPayload(), [
        'X-Stalwart-Secret' => 'wrong-secret',
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'Unauthorized']);
});

test('accepts valid hook payload and dispatches job', function () {
    Queue::fake();

    $response = $this->postJson('/api/email/inbound', mtaHookPayload(), [
        'X-Stalwart-Secret' => 'test-secret-123',
    ]);

    $response->assertOk()
        ->assertJson(['action' => 'accept']);

    Queue::assertPushed(ProcessEmailMessage::class, function ($job) {
        return $job->parsed['from'] === 'markc@renta.net'
            && $job->parsed['subject'] === 'Hello agent'
            && str_contains($job->parsed['body'], 'What is the weather today?');
    });
});

test('skips non-allowlisted sender but returns accept', function () {
    Queue::fake();

    $payload = mtaHookPayload([
        'message' => [
            'headers' => [
                ['From', 'stranger@evil.com'],
                ['To', 'claw@kanary.org'],
                ['Subject', 'Spam'],
                ['Message-ID', '<spam@evil.com>'],
            ],
            'contents' => "Buy now!\r\n",
        ],
    ]);

    $response = $this->postJson('/api/email/inbound', $payload, [
        'X-Stalwart-Secret' => 'test-secret-123',
    ]);

    $response->assertOk()
        ->assertJson(['action' => 'accept']);

    Queue::assertNothingPushed();
});

test('dedup skips already-processed message_id', function () {
    Queue::fake();

    $session = AgentSession::factory()->email()->create();
    EmailThread::factory()->create([
        'session_id' => $session->id,
        'message_id' => '<hook-test-1@renta.net>',
        'direction' => 'inbound',
    ]);

    $response = $this->postJson('/api/email/inbound', mtaHookPayload(), [
        'X-Stalwart-Secret' => 'test-secret-123',
    ]);

    $response->assertOk()
        ->assertJson(['action' => 'accept']);

    Queue::assertNothingPushed();
});

test('handles missing message body gracefully', function () {
    Queue::fake();

    $payload = mtaHookPayload([
        'message' => [
            'headers' => [
                ['From', 'markc@renta.net'],
                ['To', 'claw@kanary.org'],
                ['Subject', 'Empty body'],
                ['Message-ID', '<empty-body@renta.net>'],
            ],
            'contents' => '',
        ],
    ]);

    $response = $this->postJson('/api/email/inbound', $payload, [
        'X-Stalwart-Secret' => 'test-secret-123',
    ]);

    $response->assertOk()
        ->assertJson(['action' => 'accept']);

    Queue::assertPushed(ProcessEmailMessage::class, function ($job) {
        return $job->parsed['from'] === 'markc@renta.net'
            && $job->parsed['subject'] === 'Empty body';
    });
});

test('handles completely empty payload gracefully', function () {
    Queue::fake();

    $payload = [
        'context' => ['stage' => 'DATA'],
        'envelope' => ['from' => ['address' => ''], 'to' => []],
        'message' => ['headers' => [], 'serverHeaders' => [], 'contents' => '', 'size' => 0],
    ];

    $response = $this->postJson('/api/email/inbound', $payload, [
        'X-Stalwart-Secret' => 'test-secret-123',
    ]);

    $response->assertOk()
        ->assertJson(['action' => 'accept']);

    Queue::assertNothingPushed();
});

test('returns 401 when secret is not configured', function () {
    config(['channels.email.mta_hook.secret' => null]);

    $response = $this->postJson('/api/email/inbound', mtaHookPayload(), [
        'X-Stalwart-Secret' => 'anything',
    ]);

    $response->assertStatus(401);
});
