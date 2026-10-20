<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp\Tools;

use App\Exception\ValidationException;
use App\Mcp\Tools\StopwatchArgument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StopwatchArgumentTest extends TestCase
{
    /** @return array<string, array{mixed, mixed}> */
    public static function accepted(): array
    {
        return [
            'null leaves it' => [null, null],
            'empty object removes it' => [[], []],
            'running' => [
                ['startedAt' => '2026-10-20T14:00:00.000Z', 'total' => 600],
                ['startedAt' => '2026-10-20T14:00:00.000Z', 'total' => 600],
            ],
            'stopped' => [['startedAt' => null, 'total' => 1800], ['startedAt' => null, 'total' => 1800]],
            'offset, no seconds' => [
                ['startedAt' => '2026-10-20T10:00-04:00', 'total' => 0],
                ['startedAt' => '2026-10-20T10:00-04:00', 'total' => 0],
            ],
            'total as a string' => [['startedAt' => null, 'total' => '90'], ['startedAt' => null, 'total' => 90]],
        ];
    }

    #[DataProvider('accepted')]
    public function testAccepts(mixed $value, mixed $expected): void
    {
        $this->assertSame($expected, StopwatchArgument::from($value));
    }

    /** @return array<string, array{mixed, string}> */
    public static function rejected(): array
    {
        return [
            'not an object' => ['running', 'stopwatch must be an object'],
            'no total' => [['startedAt' => null], 'needs both startedAt'],
            'no startedAt' => [['total' => 0], 'needs both startedAt'],
            'date only' => [['startedAt' => '2026-10-20', 'total' => 0], 'startedAt must be an ISO 8601'],
            'no time zone' => [['startedAt' => '2026-10-20T14:00:00', 'total' => 0], 'startedAt must be an ISO 8601'],
            'impossible date' => [['startedAt' => '2026-13-40T14:00:00Z', 'total' => 0], 'startedAt must be an ISO 8601'],
            'negative total' => [['startedAt' => null, 'total' => -1], 'total must be a whole number of seconds'],
            'null total' => [['startedAt' => null, 'total' => null], 'total must be a whole number of seconds'],
            'fractional total' => [['startedAt' => null, 'total' => 1.5], 'stopwatch.total must be a whole number'],
        ];
    }

    #[DataProvider('rejected')]
    public function testRejects(mixed $value, string $message): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($message);
        StopwatchArgument::from($value);
    }
}
