<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DateOrder;
use App\Enums\DateOrderEvidence;
use App\Exceptions\InvalidDateException;
use Carbon\CarbonImmutable;

final readonly class DateParser
{
    private const array NULL_VALUES = ['', 'null'];

    /** Words that can appear in a date without meaning anything: the Bulgarian year marker, Spanish "de", ... */
    private const array FILLER_WORDS = ['г', 'год', 'година', 'de', 'del', 'of', 'the'];

    private const string NUMERIC_DATE = '/^(\d{1,4})([\/.\-])(\d{1,2})\2(\d{1,4})(?:[T\s]+(?:[01]?\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d+)?)?\s*(?:[AaPp][Mm])?\s*(?:Z|UTC|[+-]\d{2}:?\d{2})?)?$/';

    /** A time and time zone at the end ("T10:00:00+02:00", " 10:00 AM", log style ":10:00:00 +0000"). */
    private const string TIME_AT_END = '/(?:t|\s+|:)(?:(?:[01]?\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:[.,]\d+)?)?\s*(?:[ap]\.?m\.?)?|(?:1[0-2]|0?[1-9])\s*[ap]\.?m\.?)\s*(?:z|utc|gmt|[+-]\d{2}(?::?\d{2})?)?\s*$/u';

    private const string ORDINAL_SUFFIX = '/(\d)(?:st|nd|rd|th)(?!\p{L})/u';

    private const string TOKEN = '/(\d+|\p{L}+(?:-\p{L}+)*)/u';

    private const string SEPARATOR = '/^[\s\/.,\-]*$/u';

    /** Three numbers with the year last, the only shape where day and month can be swapped. */
    private const string YEAR_LAST_SHAPE = '/^\D*\d{1,2}\D+\d{1,2}\D+\d{2}(?:\d{2})?\D*$/u';

    public function __construct(
        private DateNames $names,
        public DateOrder $ambiguousOrder = DateOrder::DayFirst,
    ) {}

    public function withAmbiguousOrder(DateOrder $order): self
    {
        return new self($this->names, $order);
    }

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
            ?? $this->fromTokens($value)
            ?? throw InvalidDateException::for($value);
    }

    /**
     * What a value proves about the day/month order of numeric dates with the
     * year last. Only looks at the numbers; the value is not fully parsed.
     */
    public function dateOrderEvidence(string $value): DateOrderEvidence
    {
        $value = trim($value);

        if (preg_match(self::NUMERIC_DATE, $value, $m)) {
            $numbers = [$m[1], $m[3], $m[4]];
        } elseif (preg_match(self::YEAR_LAST_SHAPE, $value) && ($tokens = $this->tokenize($value)) !== null
            && $tokens['month'] === null && $tokens['separatedAlike'] && count($tokens['numbers']) === 3) {
            $numbers = $tokens['numbers'];
        } else {
            return DateOrderEvidence::None;
        }

        [$first, $second, $year] = $numbers;

        if (strlen($first) > 2 || strlen($second) > 2 || ! in_array(strlen($year), [2, 4], true)) {
            return DateOrderEvidence::None;
        }

        [$first, $second] = [(int) $first, (int) $second];

        return match (true) {
            $first < 1 || $second < 1 || ($first > 12 && $second > 12) || $first === $second => DateOrderEvidence::None,
            $first > 12 => DateOrderEvidence::DayFirst,
            $second > 12 => DateOrderEvidence::MonthFirst,
            default => DateOrderEvidence::Ambiguous,
        };
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

    /**
     * Fast path for the most common numeric dates: 2013-11-01, 01/11/2013, 01.11.13 10:00, ...
     */
    private function fromNumeric(string $value): ?CarbonImmutable
    {
        if (! preg_match(self::NUMERIC_DATE, $value, $m)) {
            return null;
        }

        return $this->fromNumbers([$m[1], $m[3], $m[4]]);
    }

    /**
     * Any other date: numbers and words in any order, with any separators.
     */
    private function fromTokens(string $value): ?CarbonImmutable
    {
        $tokens = $this->tokenize($value);

        if ($tokens === null) {
            return null;
        }

        ['numbers' => $numbers, 'month' => $month, 'weekday' => $weekday] = $tokens;

        $date = match (true) {
            $month !== null && count($numbers) === 2 => $this->withMonthName($numbers, $month),
            $month === null && count($numbers) === 3 && $tokens['separatedAlike'] => $this->fromNumbers($numbers),
            default => null,
        };

        // A weekday that doesn't match the date rejects the value.
        if ($date === null || ($weekday !== null && $date->dayOfWeek !== $weekday)) {
            return null;
        }

        return $date;
    }

    /**
     * Splits a date into numbers and words after removing the time, the time zone
     * and ordinal suffixes. Returns null for anything that is not a number, a
     * known word or a separator, so unknown words are never ignored.
     *
     * @return array{numbers: list<string>, month: ?int, weekday: ?int, separatedAlike: bool}|null
     */
    private function tokenize(string $value): ?array
    {
        $value = preg_replace([self::TIME_AT_END, self::ORDINAL_SUFFIX], ['', '$1'], mb_strtolower(trim($value)));
        $parts = preg_split(self::TOKEN, $value, -1, PREG_SPLIT_DELIM_CAPTURE);

        $numbers = [];
        $separators = [];
        $months = [];
        $weekdays = [];
        $separator = '';
        $afterNumber = false;

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                if (! preg_match(self::SEPARATOR, $part)) {
                    return null;
                }
                $separator = preg_replace('/\s+/', ' ', $part);

                continue;
            }

            if (ctype_digit($part)) {
                if ($afterNumber) {
                    $separators[] = $separator;
                }
                $numbers[] = $part;
                $afterNumber = true;

                continue;
            }

            $afterNumber = false;

            if (($month = $this->names->month($part)) !== null) {
                $months[] = $month;
            } elseif (($weekday = $this->names->weekday($part)) !== null) {
                $weekdays[] = $weekday;
            } elseif (! in_array($part, self::FILLER_WORDS, true)) {
                return null;
            }
        }

        if (count($months) > 1 || count($weekdays) > 1) {
            return null;
        }

        return [
            'numbers' => $numbers,
            'month' => $months[0] ?? null,
            'weekday' => $weekdays[0] ?? null,
            'separatedAlike' => count($separators) === count($numbers) - 1 && count(array_unique($separators)) <= 1,
        ];
    }

    /**
     * Year-month-day when the first number has 4 digits, otherwise day and
     * month (in the configured order unless a number above 12 decides) and the year.
     *
     * @param  list<string>  $numbers
     */
    private function fromNumbers(array $numbers): ?CarbonImmutable
    {
        [$first, $second, $third] = $numbers;

        if (strlen($first) === 4) {
            return strlen($second) <= 2 && strlen($third) <= 2 ? $this->makeDate((int) $first, (int) $second, (int) $third) : null;
        }

        if (strlen($first) > 2 || strlen($second) > 2 || ! in_array(strlen($third), [2, 4], true)) {
            return null;
        }

        [$first, $second, $year] = [(int) $first, (int) $second, $this->expandYear($third)];

        $dayFirst = match (true) {
            $first > 12 => true,
            $second > 12 => false,
            default => $this->ambiguousOrder === DateOrder::DayFirst,
        };

        return $dayFirst ? $this->makeDate($year, $second, $first) : $this->makeDate($year, $first, $second);
    }

    /**
     * With a month name, a 4-digit number is the year and the other one the day.
     * With two short numbers the last one is the year ("Nov 01 13").
     *
     * @param  list<string>  $numbers
     */
    private function withMonthName(array $numbers, int $month): ?CarbonImmutable
    {
        [$day, $year] = strlen($numbers[0]) === 4 ? [$numbers[1], $numbers[0]] : $numbers;

        if (strlen($day) > 2 || ! in_array(strlen($year), [2, 4], true)) {
            return null;
        }

        return $this->makeDate($this->expandYear($year), $month, (int) $day);
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
