<?php

declare(strict_types=1);

namespace App\Http;

interface RemoteFileFetcherInterface
{
    /**
     * Download a file a caller passed by URL, from an allowed host only.
     *
     * @throws \App\Exception\ValidationException when the URL is not allowed or the download fails
     */
    public function fetch(string $url): string;
}
