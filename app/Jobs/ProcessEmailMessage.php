<?php

namespace App\Jobs;

use App\DTOs\IncomingMessage;
use App\Enums\ContentSource;
use App\Mail\AgentReply;
use App\Models\EmailThread;
use App\Models\User;
use App\Services\Agent\AgentRuntime;
use App\Services\Email\EmailParserService;
use App\Services\Security\ContentSanitizer;
use App\Services\Security\InjectionAuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ProcessEmailMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public array $parsed,
    ) {}

    public function handle(AgentRuntime $runtime): void
    {
        $from = $this->parsed['from'];
        $subject = $this->parsed['subject'];
        $body = $this->parsed['body'];
        $messageId = $this->parsed['message_id'] ?? ('<'.Str::uuid().'@unknown>');
        $inReplyTo = $this->parsed['in_reply_to'];
        $references = $this->parsed['references'] ?? '';

        // Validate sender against allowlist
        $allowList = config('channels.email.allow_from', []);
        if (! empty($allowList) && ! in_array($from, $allowList)) {
            Log::info('ProcessEmailMessage: sender not in allowlist', ['from' => $from]);

            return;
        }

        // Resolve user by email
        $user = User::where('email', $from)->first();
        if (! $user) {
            Log::info('ProcessEmailMessage: no matching user', ['from' => $from]);

            return;
        }

        // Find existing session via In-Reply-To or normalized subject
        $session = $this->resolveSession($inReplyTo, $subject, $user->id);

        $sessionKey = $session
            ? $session->session_key
            : 'email.'.$user->id.'.'.Str::uuid();

        // Sanitize email body for prompt injection
        $sanitizer = app(ContentSanitizer::class);
        $sanitizeResult = $sanitizer->sanitize($body, ContentSource::EmailBody, 'standard');

        if ($sanitizeResult->injectionDetected) {
            $auditLog = app(InjectionAuditLog::class);
            $auditLog->log($sanitizeResult);

            Log::warning('ProcessEmailMessage: injection detected in email body', [
                'from' => $from,
                'patterns' => $sanitizeResult->detections,
            ]);
        }

        $body = $sanitizeResult->content;

        $parser = app(EmailParserService::class);
        $normalizedSubject = $parser->normalizeSubject($subject);

        // Build IncomingMessage and process
        $incoming = new IncomingMessage(
            channel: 'email',
            sessionKey: $sessionKey,
            content: $body,
            sender: $from,
            userId: $user->id,
        );

        $responseText = $runtime->handleMessage($incoming);

        // Reload session (may have been created by SessionResolver)
        $session = \App\Models\AgentSession::where('session_key', $sessionKey)->first();

        // Update title to normalized subject if it's a new session
        if ($session && $session->title === 'New Chat' && $normalizedSubject) {
            $session->update(['title' => mb_substr($normalizedSubject, 0, 60)]);
        }

        // Record inbound email thread entry (idempotent for retries)
        EmailThread::firstOrCreate(
            ['message_id' => $messageId],
            [
                'session_id' => $session->id,
                'from_address' => $from,
                'to_address' => config('channels.email.address'),
                'subject' => $subject,
                'in_reply_to' => $inReplyTo,
                'references' => array_filter(explode(' ', $references)),
                'direction' => 'inbound',
            ],
        );

        // Build outbound message ID and references chain
        $outboundMessageId = uniqid('claw-', true).'@'.$this->getDomain();
        $outboundReferences = trim($references.' '.$messageId);

        // Send reply
        Mail::to($from)->send(new AgentReply(
            replyBody: $responseText,
            originalSubject: $normalizedSubject,
            originalMessageId: $messageId,
            references: $outboundReferences,
        ));

        // Record outbound email thread entry
        EmailThread::create([
            'session_id' => $session->id,
            'from_address' => config('channels.email.address'),
            'to_address' => $from,
            'subject' => 'Re: '.$normalizedSubject,
            'message_id' => $outboundMessageId,
            'in_reply_to' => $messageId,
            'references' => array_filter(explode(' ', $outboundReferences)),
            'direction' => 'outbound',
        ]);

        Log::info('ProcessEmailMessage: processed', [
            'from' => $from,
            'session' => $sessionKey,
            'subject' => $subject,
        ]);
    }

    protected function resolveSession(?string $inReplyTo, string $subject, int $userId): ?\App\Models\AgentSession
    {
        // Try matching via In-Reply-To header → existing outbound message_id
        if ($inReplyTo) {
            $thread = EmailThread::where('message_id', $inReplyTo)->first();
            if ($thread) {
                return $thread->session;
            }
        }

        // Fall back to normalized subject match for this user's email sessions
        $parser = app(EmailParserService::class);
        $normalized = $parser->normalizeSubject($subject);

        if ($normalized) {
            $thread = EmailThread::where('from_address', config('channels.email.address'))
                ->where('direction', 'outbound')
                ->whereHas('session', fn ($q) => $q->where('user_id', $userId)->where('channel', 'email'))
                ->get()
                ->first(function ($t) use ($normalized, $parser) {
                    return $parser->normalizeSubject($t->subject) === $normalized;
                });

            if ($thread) {
                return $thread->session;
            }
        }

        return null;
    }

    protected function getDomain(): string
    {
        $address = config('channels.email.address', 'agent@localhost');

        return Str::after($address, '@');
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessEmailMessage failed', [
            'from' => $this->parsed['from'] ?? 'unknown',
            'subject' => $this->parsed['subject'] ?? 'unknown',
            'error' => $e->getMessage(),
        ]);
    }
}
