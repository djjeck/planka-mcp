<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\ValidationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Downloads files that callers pass by URL (e.g. a photo served by a chat gateway), so a client
 * never has to put a file on this server's disk or push its bytes through a tool call.
 *
 * Fetching arbitrary URLs from inside the network would let any caller probe internal services,
 * so only hosts named in PLANKA_UPLOAD_URL_ALLOWED_HOSTS are fetched (none by default), redirects
 * are not followed (one could point anywhere), and the body is capped.
 */
final class RemoteFileFetcher implements RemoteFileFetcherInterface
{
    public const int MAX_BYTES = 25 * 1024 * 1024;
    private const int TIMEOUT_SECONDS = 30;

    /** @param list<string> $allowedHosts lowercase host names */
    public function __construct(private readonly array $allowedHosts) {}

    public function fetch(string $url): string
    {
        $parts = parse_url($url) ?: [];
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!\in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new ValidationException('url must be an absolute http(s) URL.');
        }
        if (!\in_array($host, $this->allowedHosts, true)) {
            throw new ValidationException(sprintf(
                'Host "%s" is not allowed for uploads by URL (PLANKA_UPLOAD_URL_ALLOWED_HOSTS).',
                $host,
            ));
        }

        try {
            $response = Http::withoutRedirecting()->timeout(self::TIMEOUT_SECONDS)->get($url);
        } catch (ConnectionException) {
            throw new ValidationException('Unable to download ' . $url);
        }

        if (!$response->successful()) {
            throw new ValidationException(sprintf('Downloading %s failed: HTTP %d', $url, $response->status()));
        }

        $body = $response->body();
        if (\strlen($body) > self::MAX_BYTES) {
            throw new ValidationException(sprintf('File at %s exceeds %d bytes.', $url, self::MAX_BYTES));
        }

        return $body;
    }
}
