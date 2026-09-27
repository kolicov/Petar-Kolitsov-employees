<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DateOrder;
use App\Exceptions\InvalidDateException;
use Carbon\CarbonImmutable;
use DateTimeImmutable;

final readonly class DateParser
{
    private const array NULL_VALUES = ['', 'null'];

    private const array TEXT_FORMATS = [
        'j M Y',
        'j-M-Y',
        'j M y',
        'j-M-y',
        'M j, Y',
        'M j Y',
        'Y-M-d',
    ];

    private const string TIME_SUFFIX = '(?:[T\s]+(?:[01]?\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d+)?)?\s*(?:[AaPp][Mm])?\s*(?:Z|UTC|[+-]\d{2}:?\d{2})?)?';

    public function __construct(
        private DateOrder $ambiguousOrder = DateOrder::DayFirst,
    ) {}

    public function isNull(string $value): bool
    {
        return in_array(strtolower(trim($value)), self::NULL_VALUES, true);
    }

    /**
     * @throws InvalidDateException
     */
    public function parseOrToday(string $value): CarbonImmutable
    {
        return $this->isNull($value) ? CarbonImmutable::today() : $this->parse($value);
    }

    /**
     * @throws InvalidDateException
     */
    public function parse(string $value): CarbonImmutable
    {
        $value = trim($value);

        if ($value === '') {
            throw InvalidDateException::for($value);
        }

        return $this->fromTimestamp($value)
            ?? $this->fromCompactIso($value)
            ?? $this->fromNumeric($value)
            ?? $this->fromText($value)
            ?? throw InvalidDateException::for($value);
    }

    private function fromTimestamp(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{9,10}$/', $value)) {
            $seconds = (int) $value;
        } elseif (preg_match('/^\d{13}$/', $value)) {
            $seconds = intdiv((int) $value, 1000);
        } else {
            return null;
        }

        $date = CarbonImmutable::createFromTimestampUTC($seconds);

        return $this->makeDate($date->year, $date->month, $date->day);
    }

    private function fromCompactIso(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m)) {
            return null;
        }

        return $this->makeDate((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    private function fromNumeric(string $value): ?CarbonImmutable
    {
        $pattern = '/^(\d{1,4})([\/.\-])(\d{1,2})\2(\d{1,4})'.self::TIME_SUFFIX.'$/';

        if (! preg_match($pattern, $value, $m)) {
            return null;
        }

        [$first, $second, $third] = [$m[1], (int) $m[3], $m[4]];

        if (strlen($first) === 4) {
            return strlen($third) <= 2 ? $this->makeDate((int) $first, $second, (int) $third) : null;
        }

        if (strlen($first) > 2 || ! in_array(strlen($third), [2, 4], true)) {
            return null;
        }

        $year = $this->expandYear($third);
        $first = (int) $first;

        $dayFirst = match (true) {
            $first > 12 => true,
            $second > 12 => false,
            default => $this->ambiguousOrder === DateOrder::DayFirst,
        };

        return $dayFirst
            ? $this->makeDate($year, $second, $first)
            : $this->makeDate($year, $first, $second);
    }

    private function fromText(string $value): ?CarbonImmutable
    {
        $value = preg_replace(['/\s+/', '/(\d)(st|nd|rd|th)\b/i'], [' ', '$1'], $value);

        $weekday = null;
        if (preg_match('/^(mon|tue|wed|thu|fri|sat|sun)[a-z]*\.?,?\s+(.+)$/i', $value, $m)) {
            [$weekday, $value] = [strtolower($m[1]), $m[2]];
        }

        if (! preg_match('/[a-z]/i', $value) || preg_match_all('/\d+/', $value) < 2) {
            return null;
        }

        $date = $this->fromTextFormats($value) ?? $this->fromFreeText($value);

        if ($date === null || ($weekday !== null && strtolower($date->format('D')) !== $weekday)) {
            return null;
        }

        return $date;
    }

    private function fromTextFormats(string $value): ?CarbonImmutable
    {
        foreach (self::TEXT_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date !== false && DateTimeImmutable::getLastErrors() === false) {
                return $this->makeDate((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
            }
        }

        return null;
    }

    private function fromFreeText(string $value): ?CarbonImmutable
    {
        $parsed = date_parse($value);

        $isComplete = $parsed['year'] !== false && $parsed['month'] !== false && $parsed['day'] !== false;

        if (! $isComplete || $parsed['error_count'] > 0 || $parsed['warning_count'] > 0 || isset($parsed['relative'])) {
            return null;
        }

        return $this->makeDate($parsed['year'], $parsed['month'], $parsed['day']);
    }

    private function expandYear(string $year): int
    {
        if (strlen($year) === 4) {
            return (int) $year;
        }

        $year = (int) $year;

        return $year < 70 ? 2000 + $year : 1900 + $year;
    }

    private function makeDate(int $year, int $month, int $day): ?CarbonImmutable
    {
        if ($year < 1000 || $year > 9999 || ! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day);
    }
}
