<?php

use App\Jobs\ProcessEmailMessage;
use App\Services\Email\MailboxService;
use Illuminate\Support\Facades\Queue;

test('dispatches jobs for unseen messages', function () {
    Queue::fake();
    config(['channels.email.enabled' => true]);
    config(['channels.email.allow_from' => ['markc@renta.net']]);

    $raw = implode("\n", [
        'From: markc@renta.net',
        'To: claw@kanary.org',
        'Subject: Hello',
        'Message-ID: <test1@renta.net>',
        '',
        'Test message body.',
    ]);

    $mock = Mockery::mock(MailboxService::class);
    $mock->shouldReceive('connect')->once();
    $mock->shouldReceive('fetchUnseen')->once()->andReturn([
        ['uid' => 1, 'raw' => $raw],
    ]);
    $mock->shouldReceive('markSeen')->once()->with(1);
    $mock->shouldReceive('disconnect')->once();
    $this->app->instance(MailboxService::class, $mock);

    $this->artisan('agent:check-mail')
        ->expectsOutputToContain('1 dispatched')
        ->assertSuccessful();

    Queue::assertPushed(ProcessEmailMessage::class, 1);
});

test('skips when email channel is disabled', function () {
    Queue::fake();
    config(['channels.email.enabled' => false]);

    $this->artisan('agent:check-mail')
        ->expectsOutputToContain('disabled')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('skips non-allowlisted senders', function () {
    Queue::fake();
    config(['channels.email.enabled' => true]);
    config(['channels.email.allow_from' => ['allowed@example.com']]);

    $raw = implode("\n", [
        'From: stranger@evil.com',
        'To: claw@kanary.org',
        'Subject: Spam',
        '',
        'Buy now!',
    ]);

    $mock = Mockery::mock(MailboxService::class);
    $mock->shouldReceive('connect')->once();
    $mock->shouldReceive('fetchUnseen')->once()->andReturn([
        ['uid' => 1, 'raw' => $raw],
    ]);
    $mock->shouldReceive('markSeen')->once()->with(1);
    $mock->shouldReceive('disconnect')->once();
    $this->app->instance(MailboxService::class, $mock);

    $this->artisan('agent:check-mail')
        ->expectsOutputToContain('0 dispatched, 1 skipped')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('handles connection failure gracefully', function () {
    config(['channels.email.enabled' => true]);
    config(['channels.email.protocol' => 'jmap']);

    $mock = Mockery::mock(MailboxService::class);
    $mock->shouldReceive('connect')->once()->andThrow(new \RuntimeException('Connection refused'));
    $this->app->instance(MailboxService::class, $mock);

    $this->artisan('agent:check-mail')
        ->expectsOutputToContain('jmap connection failed')
        ->assertFailed();
});

test('dispatches multiple messages', function () {
    Queue::fake();
    config(['channels.email.enabled' => true]);
    config(['channels.email.allow_from' => []]);

    $makeRaw = fn (int $i) => implode("\n", [
        "From: user{$i}@example.com",
        'To: claw@kanary.org',
        "Subject: Message {$i}",
        "Message-ID: <msg{$i}@example.com>",
        '',
        "Body {$i}.",
    ]);

    $mock = Mockery::mock(MailboxService::class);
    $mock->shouldReceive('connect')->once();
    $mock->shouldReceive('fetchUnseen')->once()->andReturn([
        ['uid' => 1, 'raw' => $makeRaw(1)],
        ['uid' => 2, 'raw' => $makeRaw(2)],
        ['uid' => 3, 'raw' => $makeRaw(3)],
    ]);
    $mock->shouldReceive('markSeen')->times(3);
    $mock->shouldReceive('disconnect')->once();
    $this->app->instance(MailboxService::class, $mock);

    $this->artisan('agent:check-mail')
        ->expectsOutputToContain('3 dispatched')
        ->assertSuccessful();

    Queue::assertPushed(ProcessEmailMessage::class, 3);
});
