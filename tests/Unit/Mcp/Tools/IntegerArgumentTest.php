<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp\Tools;

use App\Exception\ValidationException;
use App\Mcp\Tools\IntegerArgument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IntegerArgumentTest extends TestCase
{
    /** @return array<string, array{mixed, ?int}> */
    public static function accepted(): array
    {
        return [
            'null' => [null, null],
            'int' => [65536, 65536],
            'whole float' => [3.0, 3],
            'numeric string' => ['65536', 65536],
            'padded string' => [' 42 ', 42],
            'thousands separator' => ['65,536', 65536],
            'negative' => ['-1', -1],
            'trailing zero decimals' => ['7.00', 7],
            'empty string' => ['', null],
        ];
    }

    #[DataProvider('accepted')]
    public function testAccepts(mixed $value, ?int $expected): void
    {
        $this->assertSame($expected, IntegerArgument::from($value, 'position'));
    }

    /** @return array<string, array{mixed}> */
    public static function rejected(): array
    {
        return [
            'fraction' => [1.5],
            'fractional string' => ['1.5'],
            'words' => ['top'],
            'bool' => [true],
            'array' => [[1]],
        ];
    }

    #[DataProvider('rejected')]
    public function testRejectsByFieldName(mixed $value): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('position must be a whole number');
        IntegerArgument::from($value, 'position');
    }
}
