<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Exception\ValidationException;

/**
 * Reads a card stopwatch tool argument: Planka 2's `{startedAt, total}`, where `startedAt` is
 * when it was started (ISO 8601) or null while stopped, and `total` the seconds it ran before
 * that. Null (or absent) leaves the stopwatch as it is, since clients send null for any optional
 * argument they leave out; an empty object `{}` removes it from the card, returned as [].
 */
final class StopwatchArgument
{
    /** @return array{startedAt: ?string, total: int}|array{}|null */
    public static function from(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new ValidationException(sprintf('stopwatch must be an object, got %s', json_encode($value)));
        }
        if ($value === []) {
            return [];
        }
        if (!array_key_exists('startedAt', $value) || !array_key_exists('total', $value)) {
            throw new ValidationException('stopwatch needs both startedAt (null when stopped) and total');
        }

        $startedAt = $value['startedAt'];
        if ($startedAt !== null && (!is_string($startedAt) || !self::isIsoDateTime($startedAt))) {
            throw new ValidationException(sprintf('stopwatch.startedAt must be an ISO 8601 date-time or null, got %s', json_encode($startedAt)));
        }
        $total = IntegerArgument::from($value['total'], 'stopwatch.total');
        if ($total === null || $total < 0) {
            throw new ValidationException(sprintf('stopwatch.total must be a whole number of seconds, 0 or more, got %s', json_encode($value['total'])));
        }

        return ['startedAt' => $startedAt, 'total' => $total];
    }

    private static function isIsoDateTime(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/', $value) !== 1) {
            return false;
        }

        try {
            new \DateTimeImmutable($value);

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
