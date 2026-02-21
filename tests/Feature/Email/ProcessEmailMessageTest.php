<?php

use App\Jobs\ProcessEmailMessage;
use App\Mail\AgentReply;
use App\Models\Agent;
use App\Models\AgentSession;
use App\Models\EmailThread;
use App\Models\User;
use App\Services\Agent\AgentRuntime;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'markc@renta.net']);
    $this->agent = Agent::factory()->create();

    config(['channels.email.enabled' => true]);
    config(['channels.email.allow_from' => ['markc@renta.net']]);
    config(['channels.email.address' => 'ai@kanary.org']);
});

test('processes email and sends reply', function () {
    Mail::fake();

    $agent = $this->agent;
    $mock = Mockery::mock(AgentRuntime::class);
    $mock->shouldReceive('handleMessage')
        ->once()
        ->andReturnUsing(function ($msg) use ($agent) {
            // Simulate what SessionResolver does
            AgentSession::firstOrCreate(
                ['session_key' => $msg->sessionKey],
                [
                    'agent_id' => $agent->id,
                    'user_id' => $msg->userId,
                    'title' => 'New Chat',
                    'channel' => 'email',
                    'trust_level' => 'standard',
                ],
            );

            return 'Hello from the agent!';
        });
    $this->app->instance(AgentRuntime::class, $mock);

    $parsed = [
        'from' => 'markc@renta.net',
        'to' => 'ai@kanary.org',
        'subject' => 'Hello agent',
        'date' => 'Tue, 18 Feb 2026 10:00:00 +1000',
        'message_id' => '<abc123@renta.net>',
        'in_reply_to' => null,
        'references' => null,
        'body' => 'What is the weather?',
        'headers' => [],
        'raw_size' => 200,
    ];

    ProcessEmailMessage::dispatchSync($parsed);

    Mail::assertSent(AgentReply::class, function (AgentReply $mail) {
        return $mail->hasTo('markc@renta.net')
            && $mail->replyBody === 'Hello from the agent!'
            && $mail->originalMessageId === '<abc123@renta.net>';
    });

    expect(EmailThread::where('direction', 'inbound')->count())->toBe(1)
        ->and(EmailThread::where('direction', 'outbound')->count())->toBe(1);
});

test('rejects non-allowlisted sender', function () {
    Mail::fake();

    config(['channels.email.allow_from' => ['allowed@example.com']]);

    $parsed = [
        'from' => 'stranger@evil.com',
        'to' => 'ai@kanary.org',
        'subject' => 'Spam',
        'date' => null,
        'message_id' => '<spam@evil.com>',
        'in_reply_to' => null,
        'references' => null,
        'body' => 'Buy now!',
        'headers' => [],
        'raw_size' => 100,
    ];

    ProcessEmailMessage::dispatchSync($parsed);

    Mail::assertNothingSent();
    expect(EmailThread::count())->toBe(0);
});

test('rejects sender with no matching user', function () {
    Mail::fake();

    config(['channels.email.allow_from' => ['unknown@example.com']]);

    $parsed = [
        'from' => 'unknown@example.com',
        'to' => 'ai@kanary.org',
        'subject' => 'Hello',
        'date' => null,
        'message_id' => '<unknown@example.com>',
        'in_reply_to' => null,
        'references' => null,
        'body' => 'Hi there.',
        'headers' => [],
        'raw_size' => 100,
    ];

    ProcessEmailMessage::dispatchSync($parsed);

    Mail::assertNothingSent();
    expect(EmailThread::count())->toBe(0);
});

test('reuses existing session via In-Reply-To', function () {
    Mail::fake();

    $mock = Mockery::mock(AgentRuntime::class);
    $mock->shouldReceive('handleMessage')->once()->andReturn('Follow-up reply');
    $this->app->instance(AgentRuntime::class, $mock);

    // Create an existing session with an outbound thread entry
    $session = AgentSession::factory()->email()->create([
        'user_id' => $this->user->id,
        'agent_id' => $this->agent->id,
    ]);
    $outboundThread = EmailThread::factory()->outbound()->create([
        'session_id' => $session->id,
        'message_id' => '<reply-1@kanary.org>',
        'subject' => 'Re: Hello',
    ]);

    $parsed = [
        'from' => 'markc@renta.net',
        'to' => 'ai@kanary.org',
        'subject' => 'Re: Hello',
        'date' => null,
        'message_id' => '<reply-2@renta.net>',
        'in_reply_to' => '<reply-1@kanary.org>',
        'references' => '<reply-1@kanary.org>',
        'body' => 'Follow-up question.',
        'headers' => [],
        'raw_size' => 150,
    ];

    ProcessEmailMessage::dispatchSync($parsed);

    Mail::assertSent(AgentReply::class, 1);

    // Should reuse the existing session (the mock received the same session_key)
    $mock->shouldHaveReceived('handleMessage')->once()->withArgs(function ($msg) use ($session) {
        return $msg->sessionKey === $session->session_key;
    });

    // Both inbound entries should be on the same session
    $threads = EmailThread::where('session_id', $session->id)->get();
    expect($threads->where('direction', 'inbound')->count())->toBe(1)
        ->and($threads->where('direction', 'outbound')->count())->toBe(2);
});

test('sets session title from normalized subject', function () {
    Mail::fake();

    $agent = $this->agent;
    $mock = Mockery::mock(AgentRuntime::class);
    $mock->shouldReceive('handleMessage')
        ->once()
        ->andReturnUsing(function ($msg) use ($agent) {
            AgentSession::firstOrCreate(
                ['session_key' => $msg->sessionKey],
                [
                    'agent_id' => $agent->id,
                    'user_id' => $msg->userId,
                    'title' => 'New Chat',
                    'channel' => 'email',
                    'trust_level' => 'standard',
                ],
            );

            return 'OK';
        });
    $this->app->instance(AgentRuntime::class, $mock);

    $parsed = [
        'from' => 'markc@renta.net',
        'to' => 'ai@kanary.org',
        'subject' => 'Project planning for Q2',
        'date' => null,
        'message_id' => '<title-test@renta.net>',
        'in_reply_to' => null,
        'references' => null,
        'body' => 'Let us plan Q2.',
        'headers' => [],
        'raw_size' => 100,
    ];

    ProcessEmailMessage::dispatchSync($parsed);

    $session = AgentSession::where('channel', 'email')->latest()->first();
    expect($session->title)->toBe('Project planning for Q2');
});
