<?php

declare(strict_types=1);

namespace App\Validation;

use InvalidArgumentException;

class Validator
{
    /**
     * Validate an email address format.
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Verify required fields exist and are non-empty.
     *
     * @param array $data
     * @param array<string> $requiredFields
     * @return array<string> List of missing field names
     */
    public static function checkRequired(array $data, array $requiredFields): array
    {
        $missing = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    /**
     * Sanitize string input to prevent XSS.
     */
    public static function sanitizeString(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Validate value is within an allowed set.
     */
    public static function validateEnum(string $value, array $allowed): bool
    {
        return in_array($value, $allowed, true);
    }

    /**
     * Validate date string matches format (default Y-m-d) and represents a valid calendar date.
     */
    public static function validateDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTimeImmutable::createFromFormat($format, trim($date));
        return $d && $d->format($format) === trim($date);
    }
}
