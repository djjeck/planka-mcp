<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Exception\ValidationException;

/**
 * Reads a whole-number tool argument. Models send numbers as strings ("65536") as often as
 * not, and the SDK does not check arguments against the schema, so a string would reach an
 * int parameter and fail with a TypeError. Accepts an int, a whole float, or a numeric string
 * (thousands separators allowed); null stays null.
 */
final class IntegerArgument
{
    public static function from(mixed $value, string $field): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) && floor($value) === $value && is_finite($value)) {
            return (int) $value;
        }
        if (is_string($value)) {
            $text = preg_replace('/,(?=\d{3}(?:\D|$))/', '', trim($value)) ?? '';
            if ($text === '') {
                return null;
            }
            if (preg_match('/^[-+]?\d+(?:\.0*)?$/', $text) === 1) {
                return (int) $text;
            }
        }

        throw new ValidationException(sprintf('%s must be a whole number, got %s', $field, json_encode($value)));
    }
}
