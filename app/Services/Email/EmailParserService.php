<?php

namespace App\Services\Email;

class EmailParserService
{
    /**
     * Parse an email from raw MIME content.
     *
     * @return array{from: string, to: string, subject: string, date: ?string, message_id: ?string, in_reply_to: ?string, references: ?string, body: string, headers: array<string, string>, raw_size: int}
     */
    public function parse(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);

        $parts = preg_split('/\n\n/', $raw, 2);
        $headerBlock = $parts[0] ?? '';
        $body = $parts[1] ?? '';

        $headers = $this->parseHeaders($headerBlock);

        $bodyText = $this->extractBodyText($body, $headers['content-type'] ?? '');
        $bodyText = $this->stripSignature($bodyText);

        return [
            'from' => $this->parseAddress($headers['from'] ?? ''),
            'to' => $this->parseAddress($headers['to'] ?? ''),
            'subject' => $this->decodeHeader($headers['subject'] ?? ''),
            'date' => $headers['date'] ?? null,
            'message_id' => $headers['message-id'] ?? null,
            'in_reply_to' => $headers['in-reply-to'] ?? null,
            'references' => $headers['references'] ?? null,
            'body' => trim($bodyText),
            'headers' => $headers,
            'raw_size' => strlen($raw),
        ];
    }

    /**
     * Parse a quick envelope for routing decisions.
     *
     * @return array{from?: string, to?: string, subject?: string}
     */
    public function parseEnvelope(string $raw): array
    {
        $envelope = [];

        if (preg_match('/^From:\s*(.+)$/mi', $raw, $m)) {
            $envelope['from'] = $this->parseAddress(trim($m[1]));
        }

        if (preg_match('/^To:\s*(.+)$/mi', $raw, $m)) {
            $envelope['to'] = $this->parseAddress(trim($m[1]));
        }

        if (preg_match('/^Subject:\s*(.+)$/mi', $raw, $m)) {
            $envelope['subject'] = $this->decodeHeader(trim($m[1]));
        }

        return $envelope;
    }

    /**
     * Normalize a subject line for thread matching by stripping Re:/Fwd: prefixes.
     */
    public function normalizeSubject(string $subject): string
    {
        return trim(preg_replace('/^(?:(?:Re|Fwd?)\s*:\s*)+/i', '', $subject));
    }

    /**
     * @return array<string, string>
     */
    protected function parseHeaders(string $headerBlock): array
    {
        $headers = [];
        $lines = explode("\n", $headerBlock);
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^\s+(.+)$/', $line, $m) && $current !== null) {
                $headers[$current] .= ' '.trim($m[1]);

                continue;
            }

            if (preg_match('/^([A-Za-z0-9-]+):\s*(.*)$/', $line, $m)) {
                $current = strtolower($m[1]);
                $headers[$current] = trim($m[2]);
            }
        }

        return $headers;
    }

    protected function parseAddress(string $value): string
    {
        if (preg_match('/<([^>]+)>/', $value, $m)) {
            return strtolower(trim($m[1]));
        }

        return strtolower(trim($value));
    }

    protected function decodeHeader(string $value): string
    {
        if (preg_match_all('/=\?([^?]+)\?([BQ])\?([^?]+)\?=/i', $value, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $charset = $match[1];
                $encoding = strtoupper($match[2]);
                $text = $match[3];

                $decoded = match ($encoding) {
                    'B' => base64_decode($text),
                    'Q' => quoted_printable_decode(str_replace('_', ' ', $text)),
                    default => $text,
                };

                if (strtoupper($charset) !== 'UTF-8') {
                    $decoded = mb_convert_encoding($decoded, 'UTF-8', $charset);
                }

                $value = str_replace($match[0], $decoded, $value);
            }
        }

        return $value;
    }

    protected function extractBodyText(string $body, string $contentType): string
    {
        if (str_contains($contentType, 'multipart/')) {
            if (preg_match('/boundary="?([^";\s]+)"?/i', $contentType, $m)) {
                $boundary = $m[1];
                $parts = explode('--'.$boundary, $body);

                foreach ($parts as $part) {
                    if (preg_match('/Content-Type:\s*text\/plain/i', $part)) {
                        if (preg_match('/\n\n(.+)/s', $part, $m)) {
                            return $this->decodeBodyPart(trim($m[1]), $part);
                        }
                    }
                }
            }
        }

        return $this->decodeBodyPart($body, "Content-Type: $contentType");
    }

    protected function decodeBodyPart(string $content, string $partHeaders): string
    {
        if (preg_match('/Content-Transfer-Encoding:\s*(\S+)/i', $partHeaders, $m)) {
            $encoding = strtolower($m[1]);

            return match ($encoding) {
                'base64' => base64_decode($content),
                'quoted-printable' => quoted_printable_decode($content),
                default => $content,
            };
        }

        return $content;
    }

    protected function stripSignature(string $body): string
    {
        if (preg_match('/^-- $/m', $body)) {
            $parts = preg_split('/^-- $/m', $body);

            return trim($parts[0]);
        }

        $body = preg_replace('/\n*Sent from my (iPhone|Android|iPad|Samsung|Pixel).*$/is', '', $body);
        $body = preg_replace('/\n*Get Outlook for (iOS|Android).*$/is', '', $body);

        return trim($body);
    }
}
