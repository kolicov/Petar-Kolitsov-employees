<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * A calendar date as whole days since 1970-01-01. An int costs far less
 * memory than a date object, which matters for large CSV files.
 */
final class DayNumber
{
    private const int SECONDS_PER_DAY = 86_400;

    /**
     * Time of day and timezone are ignored; only the calendar date counts.
     */
    public static function fromDate(DateTimeInterface $date): int
    {
        $midnightUtc = gmmktime(0, 0, 0, (int) $date->format('n'), (int) $date->format('j'), (int) $date->format('Y'));

        return intdiv($midnightUtc, self::SECONDS_PER_DAY);
    }

    public static function toDate(int $day): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.($day * self::SECONDS_PER_DAY));
    }
}
