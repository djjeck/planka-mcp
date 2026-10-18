<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Exception\ValidationException;
use App\Http\RemoteFileFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RemoteFileFetcherTest extends TestCase
{
    private function fetcher(): RemoteFileFetcher
    {
        return new RemoteFileFetcher(['human-dm']);
    }

    public function testFetchReturnsTheBodyFromAnAllowedHost(): void
    {
        Http::fake(['human-dm:8900/*' => Http::response('jpeg-bytes', 200)]);

        $this->assertSame('jpeg-bytes', $this->fetcher()->fetch('http://human-dm:8900/files/abc.sig'));
    }

    public function testFetchMatchesTheHostCaseInsensitively(): void
    {
        Http::fake(['*' => Http::response('bytes', 200)]);

        $this->assertSame('bytes', $this->fetcher()->fetch('http://Human-DM:8900/files/abc.sig'));
    }

    public function testFetchRefusesAHostNotAllowedWithoutRequestingIt(): void
    {
        Http::fake();

        try {
            $this->fetcher()->fetch('http://planka:1337/api/users');
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('"planka" is not allowed', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function testFetchRefusesEverythingWhenNoHostIsAllowed(): void
    {
        Http::fake();

        $this->expectException(ValidationException::class);
        (new RemoteFileFetcher([]))->fetch('http://human-dm:8900/files/abc.sig');
    }

    /** @return array<string, array{string}> */
    public static function badUrls(): array
    {
        return [
            'file scheme' => ['file:///etc/passwd'],
            'no scheme' => ['human-dm/files/abc'],
            'ftp' => ['ftp://human-dm/files/abc'],
            'empty' => [''],
        ];
    }

    #[DataProvider('badUrls')]
    public function testFetchRefusesAnythingButAbsoluteHttpUrls(string $url): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('absolute http(s) URL');
        $this->fetcher()->fetch($url);
    }

    public function testFetchDoesNotFollowRedirects(): void
    {
        Http::fake(['human-dm:8900/*' => Http::response('', 302, ['Location' => 'http://planka:1337/'])]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('HTTP 302');
        $this->fetcher()->fetch('http://human-dm:8900/files/abc.sig');
    }

    public function testFetchFailsOnAnErrorStatus(): void
    {
        Http::fake(['human-dm:8900/*' => Http::response('not found', 404)]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('HTTP 404');
        $this->fetcher()->fetch('http://human-dm:8900/files/abc.sig');
    }

    public function testFetchFailsWhenTheHostCannotBeReached(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Unable to download');
        $this->fetcher()->fetch('http://human-dm:8900/files/abc.sig');
    }

    public function testFetchRefusesAFileOverTheCap(): void
    {
        Http::fake(['human-dm:8900/*' => Http::response(str_repeat('x', RemoteFileFetcher::MAX_BYTES + 1), 200)]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('exceeds');
        $this->fetcher()->fetch('http://human-dm:8900/files/abc.sig');
    }
}
