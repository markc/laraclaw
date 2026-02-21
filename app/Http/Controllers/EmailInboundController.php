<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessEmailMessage;
use App\Models\EmailThread;
use App\Services\Email\EmailParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailInboundController extends Controller
{
    /**
     * Handle Stalwart MTA Hook POST at DATA stage.
     *
     * Validates shared secret, reconstructs raw MIME from hook payload,
     * checks allowlist and dedup, then dispatches ProcessEmailMessage.
     */
    public function __invoke(Request $request, EmailParserService $parser): JsonResponse
    {
        $secret = config('channels.email.mta_hook.secret');

        if (! $secret || $request->header('X-Stalwart-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();

        $raw = $this->reconstructRawMime($payload);

        if (! $raw) {
            Log::warning('EmailInbound: empty message payload');

            return response()->json(['action' => 'accept']);
        }

        // Quick envelope check for allowlist
        $envelope = $parser->parseEnvelope($raw);
        $from = $envelope['from'] ?? '';
        $allowList = config('channels.email.allow_from', []);

        if (! empty($allowList) && ! in_array($from, $allowList)) {
            Log::info('EmailInbound: sender not in allowlist', ['from' => $from]);

            return response()->json(['action' => 'accept']);
        }

        // Full parse
        $parsed = $parser->parse($raw);
        $messageId = $parsed['message_id'] ?? null;

        // Dedup — skip already-processed messages
        if ($messageId && EmailThread::where('message_id', $messageId)->exists()) {
            Log::info('EmailInbound: duplicate message_id, skipping', ['message_id' => $messageId]);

            return response()->json(['action' => 'accept']);
        }

        ProcessEmailMessage::dispatch($parsed);

        Log::info('EmailInbound: dispatched', [
            'from' => $from,
            'subject' => $parsed['subject'] ?? '',
        ]);

        return response()->json(['action' => 'accept']);
    }

    /**
     * Reconstruct raw MIME from Stalwart MTA Hook payload.
     *
     * Headers come as [[name, value], ...], contents is the raw body text.
     */
    protected function reconstructRawMime(array $payload): ?string
    {
        $message = $payload['message'] ?? [];
        $headers = $message['headers'] ?? [];
        $serverHeaders = $message['serverHeaders'] ?? [];
        $contents = $message['contents'] ?? '';

        if (empty($headers) && empty($contents)) {
            return null;
        }

        $headerLines = [];

        foreach ($serverHeaders as $header) {
            if (is_array($header) && count($header) >= 2) {
                $headerLines[] = $header[0].': '.$header[1];
            }
        }

        foreach ($headers as $header) {
            if (is_array($header) && count($header) >= 2) {
                $headerLines[] = $header[0].': '.$header[1];
            }
        }

        return implode("\r\n", $headerLines)."\r\n\r\n".$contents;
    }
}
