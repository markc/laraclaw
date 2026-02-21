<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEmailMessage;
use App\Services\Email\EmailParserService;
use App\Services\Email\ImapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AgentCheckMail extends Command
{
    protected $signature = 'agent:check-mail';

    protected $description = 'Poll IMAP mailbox and dispatch email messages for processing';

    public function handle(ImapService $imap, EmailParserService $parser): int
    {
        if (! config('channels.email.enabled')) {
            $this->info('Email channel is disabled.');

            return self::SUCCESS;
        }

        try {
            $imap->connect();
        } catch (\Throwable $e) {
            Log::error('agent:check-mail: IMAP connection failed', ['error' => $e->getMessage()]);
            $this->error('IMAP connection failed: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            $messages = $imap->fetchUnseen();
        } catch (\Throwable $e) {
            Log::error('agent:check-mail: fetch failed', ['error' => $e->getMessage()]);
            $this->error('Failed to fetch messages: '.$e->getMessage());
            $imap->disconnect();

            return self::FAILURE;
        }

        $dispatched = 0;
        $skipped = 0;
        $allowList = config('channels.email.allow_from', []);

        foreach ($messages as $msg) {
            $envelope = $parser->parseEnvelope($msg['raw']);
            $from = $envelope['from'] ?? '';

            // Pre-filter by allowlist before dispatching
            if (! empty($allowList) && ! in_array($from, $allowList)) {
                Log::info('agent:check-mail: skipped non-allowlisted sender', ['from' => $from]);
                $imap->markSeen($msg['uid']);
                $skipped++;

                continue;
            }

            $parsed = $parser->parse($msg['raw']);
            ProcessEmailMessage::dispatch($parsed);
            $imap->markSeen($msg['uid']);
            $dispatched++;
        }

        $imap->disconnect();

        $this->info("Check mail: {$dispatched} dispatched, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
