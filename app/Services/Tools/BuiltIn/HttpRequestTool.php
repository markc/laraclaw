<?php

namespace App\Services\Tools\BuiltIn;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class HttpRequestTool implements Tool
{
    public function name(): string
    {
        return 'http_request';
    }

    public function description(): string
    {
        return 'Make an HTTP request to a URL. Supports GET and POST methods. Returns the response body (truncated to 8000 chars).';
    }

    public function handle(Request $request): string
    {
        $url = $request['url'] ?? '';
        $method = strtoupper($request['method'] ?? 'GET');
        $headers = $request['headers'] ?? [];
        $body = $request['body'] ?? null;

        if (empty($url)) {
            return 'Error: url parameter is required.';
        }

        // Block private/internal IPs
        $host = parse_url($url, PHP_URL_HOST);
        if ($this->isPrivateHost($host)) {
            return 'Error: requests to private/internal addresses are not allowed.';
        }

        try {
            $pending = Http::timeout(15)->withHeaders($headers);

            $response = match ($method) {
                'POST' => $pending->post($url, $body ? json_decode($body, true) : []),
                default => $pending->get($url),
            };

            $statusCode = $response->status();
            $responseBody = $response->body();

            // Truncate large responses
            if (mb_strlen($responseBody) > 8000) {
                $responseBody = mb_substr($responseBody, 0, 8000).'... [truncated]';
            }

            return "HTTP {$statusCode}\n\n{$responseBody}";
        } catch (\Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            $schema->string('url')
                ->description('The URL to request')
                ->required(),
            $schema->string('method')
                ->description('HTTP method: GET or POST')
                ->default('GET'),
            $schema->string('headers')
                ->description('Optional JSON object of HTTP headers'),
            $schema->string('body')
                ->description('Optional request body as JSON string (for POST)'),
        ];
    }

    protected function isPrivateHost(?string $host): bool
    {
        if (empty($host)) {
            return true;
        }

        // Block localhost and common private patterns
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'])) {
            return true;
        }

        $ip = gethostbyname($host);
        if ($ip === $host) {
            return false; // DNS resolution failed, let HTTP client handle it
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
