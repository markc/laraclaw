<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEmailMessage;
use App\Services\Email\EmailParserService;
use App\Services\Email\MailboxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AgentCheckMail extends Command
{
    protected $signature = 'agent:check-mail';

    protected $description = 'Poll mailbox for new messages and dispatch for processing';

    public function handle(MailboxService $mailbox, EmailParserService $parser): int
    {
        if (! config('channels.email.enabled')) {
            $this->info('Email channel is disabled.');

            return self::SUCCESS;
        }

        $protocol = config('channels.email.protocol', 'jmap');

        try {
            $mailbox->connect();
        } catch (\Throwable $e) {
            Log::error("agent:check-mail: {$protocol} connection failed", ['error' => $e->getMessage()]);
            $this->error("{$protocol} connection failed: ".$e->getMessage());

            return self::FAILURE;
        }

        try {
            $messages = $mailbox->fetchUnseen();
        } catch (\Throwable $e) {
            Log::error('agent:check-mail: fetch failed', ['error' => $e->getMessage()]);
            $this->error('Failed to fetch messages: '.$e->getMessage());
            $mailbox->disconnect();

            return self::FAILURE;
        }

        $dispatched = 0;
        $skipped = 0;
        $allowList = config('channels.email.allow_from', []);

        foreach ($messages as $msg) {
            $envelope = $parser->parseEnvelope($msg['raw']);
            $from = $envelope['from'] ?? '';

            if (! empty($allowList) && ! in_array($from, $allowList)) {
                Log::info('agent:check-mail: skipped non-allowlisted sender', ['from' => $from]);
                $mailbox->markSeen($msg['uid']);
                $skipped++;

                continue;
            }

            $parsed = $parser->parse($msg['raw']);
            ProcessEmailMessage::dispatch($parsed);
            $mailbox->markSeen($msg['uid']);
            $dispatched++;
        }

        $mailbox->disconnect();

        $this->info("Check mail ({$protocol}): {$dispatched} dispatched, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
