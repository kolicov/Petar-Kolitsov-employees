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
        return self::fromYearMonthDay((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
    }

    public static function fromYearMonthDay(int $year, int $month, int $day): int
    {
        return intdiv(gmmktime(0, 0, 0, $month, $day, $year), self::SECONDS_PER_DAY);
    }

    public static function toDate(int $day): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.($day * self::SECONDS_PER_DAY));
    }
}
