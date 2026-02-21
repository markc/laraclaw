<?php

use App\Services\Email\EmailParserService;

beforeEach(function () {
    $this->parser = new EmailParserService;
});

test('parses plain text email', function () {
    $raw = implode("\r\n", [
        'From: Mark <markc@renta.net>',
        'To: claw@kanary.org',
        'Subject: Hello agent',
        'Date: Tue, 18 Feb 2026 10:00:00 +1000',
        'Message-ID: <abc123@renta.net>',
        '',
        'What is the weather today?',
    ]);

    $parsed = $this->parser->parse($raw);

    expect($parsed['from'])->toBe('markc@renta.net')
        ->and($parsed['to'])->toBe('claw@kanary.org')
        ->and($parsed['subject'])->toBe('Hello agent')
        ->and($parsed['message_id'])->toBe('<abc123@renta.net>')
        ->and($parsed['body'])->toBe('What is the weather today?');
});

test('strips email signature', function () {
    $raw = implode("\n", [
        'From: user@example.com',
        'To: claw@kanary.org',
        'Subject: Test',
        '',
        'Main body text here.',
        '',
        '-- ',
        'John Doe',
        'CEO, Example Inc.',
    ]);

    $parsed = $this->parser->parse($raw);

    expect($parsed['body'])->toBe('Main body text here.');
});

test('strips mobile signature', function () {
    $raw = implode("\n", [
        'From: user@example.com',
        'To: claw@kanary.org',
        'Subject: Test',
        '',
        'Quick reply.',
        '',
        'Sent from my iPhone',
    ]);

    $parsed = $this->parser->parse($raw);

    expect($parsed['body'])->toBe('Quick reply.');
});

test('decodes MIME encoded subject', function () {
    $raw = implode("\n", [
        'From: user@example.com',
        'To: claw@kanary.org',
        'Subject: =?UTF-8?B?'.base64_encode('Héllo').'?=',
        '',
        'Body.',
    ]);

    $parsed = $this->parser->parse($raw);

    expect($parsed['subject'])->toBe('Héllo');
});

test('normalizes subject by stripping Re and Fwd prefixes', function () {
    expect($this->parser->normalizeSubject('Re: Hello'))->toBe('Hello')
        ->and($this->parser->normalizeSubject('Fwd: Hello'))->toBe('Hello')
        ->and($this->parser->normalizeSubject('Re: Fwd: Re: Hello'))->toBe('Hello')
        ->and($this->parser->normalizeSubject('re: fw: test'))->toBe('test')
        ->and($this->parser->normalizeSubject('Hello'))->toBe('Hello');
});

test('parses envelope for quick routing', function () {
    $raw = implode("\n", [
        'From: Mark <markc@renta.net>',
        'To: Agent <claw@kanary.org>',
        'Subject: Quick question',
        'Message-ID: <xyz@renta.net>',
        '',
        'Body here.',
    ]);

    $envelope = $this->parser->parseEnvelope($raw);

    expect($envelope['from'])->toBe('markc@renta.net')
        ->and($envelope['to'])->toBe('claw@kanary.org')
        ->and($envelope['subject'])->toBe('Quick question');
});

test('parses In-Reply-To and References headers', function () {
    $raw = implode("\n", [
        'From: markc@renta.net',
        'To: claw@kanary.org',
        'Subject: Re: Hello',
        'Message-ID: <reply1@renta.net>',
        'In-Reply-To: <original@kanary.org>',
        'References: <original@kanary.org>',
        '',
        'Thanks!',
    ]);

    $parsed = $this->parser->parse($raw);

    expect($parsed['in_reply_to'])->toBe('<original@kanary.org>')
        ->and($parsed['references'])->toBe('<original@kanary.org>');
});

test('handles multipart message extracting text/plain', function () {
    $boundary = 'boundary123';
    $raw = implode("\n", [
        'From: user@example.com',
        'To: claw@kanary.org',
        'Subject: Multipart test',
        'Content-Type: multipart/alternative; boundary="'.$boundary.'"',
        '',
        '--'.$boundary,
        'Content-Type: text/plain; charset=utf-8',
        '',
        'Plain text body.',
        '--'.$boundary,
        'Content-Type: text/html; charset=utf-8',
        '',
        '<p>HTML body.</p>',
        '--'.$boundary.'--',
    ]);

    $parsed = $this->parser->parse($raw);

    expect($parsed['body'])->toBe('Plain text body.');
});

test('decodes base64 body part', function () {
    $boundary = 'bound456';
    $encoded = base64_encode('Decoded content here.');
    $raw = implode("\n", [
        'From: user@example.com',
        'To: claw@kanary.org',
        'Subject: Base64 test',
        'Content-Type: multipart/alternative; boundary="'.$boundary.'"',
        '',
        '--'.$boundary,
        'Content-Type: text/plain; charset=utf-8',
        'Content-Transfer-Encoding: base64',
        '',
        $encoded,
        '--'.$boundary.'--',
    ]);

    $parsed = $this->parser->parse($raw);

    expect($parsed['body'])->toBe('Decoded content here.');
});
