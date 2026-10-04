<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

class Timezone
{
    public const DB_TIMEZONE = 'UTC';
    public const DISPLAY_TIMEZONE = 'Europe/London';

    /**
     * Convert a date/time string in UK local time (Europe/London) to UTC database format (Y-m-d H:i:s).
     *
     * @param string $dateString
     * @return string
     * @throws InvalidArgumentException
     */
    public static function londonToUtc(string $dateString): string
    {
        try {
            $londonTz = new DateTimeZone(self::DISPLAY_TIMEZONE);
            $utcTz = new DateTimeZone(self::DB_TIMEZONE);

            $dt = new DateTimeImmutable($dateString, $londonTz);
            return $dt->setTimezone($utcTz)->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            throw new InvalidArgumentException("Invalid date format for London timezone: {$dateString}", 0, $e);
        }
    }

    /**
     * Convert a UTC date/time string to Europe/London display format.
     *
     * @param string $utcDateString
     * @param string $format Default 'd M Y, H:i'
     * @return string
     * @throws InvalidArgumentException
     */
    public static function utcToLondon(string $utcDateString, string $format = 'd M Y, H:i'): string
    {
        try {
            $utcTz = new DateTimeZone(self::DB_TIMEZONE);
            $londonTz = new DateTimeZone(self::DISPLAY_TIMEZONE);

            $dt = new DateTimeImmutable($utcDateString, $utcTz);
            return $dt->setTimezone($londonTz)->format($format);
        } catch (Exception $e) {
            throw new InvalidArgumentException("Invalid UTC date format: {$utcDateString}", 0, $e);
        }
    }

    /**
     * Alias for utcToLondon formatted display.
     *
     * @param string $utcDateString
     * @param string $format
     * @return string
     */
    public static function utcToLondonDisplay(string $utcDateString, string $format = 'd M Y, H:i'): string
    {
        return self::utcToLondon($utcDateString, $format);
    }

    /**
     * Get the current timestamp in UTC (Y-m-d H:i:s).
     *
     * @return string
     */
    public static function nowUtc(): string
    {
        $utcTz = new DateTimeZone(self::DB_TIMEZONE);
        return (new DateTimeImmutable('now', $utcTz))->format('Y-m-d H:i:s');
    }

    /**
     * Check if a given date string is currently in British Summer Time (BST, UTC+1).
     *
     * @param string $utcDateString
     * @return bool
     */
    public static function isBst(string $utcDateString): bool
    {
        $utcTz = new DateTimeZone(self::DB_TIMEZONE);
        $londonTz = new DateTimeZone(self::DISPLAY_TIMEZONE);

        $dt = (new DateTimeImmutable($utcDateString, $utcTz))->setTimezone($londonTz);
        return (bool) $dt->format('I'); // '1' if Daylight Saving Time, '0' otherwise
    }
}
