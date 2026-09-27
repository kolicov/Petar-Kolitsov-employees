<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidDateException;
use App\Support\DayNumber;
use Carbon\CarbonImmutable;

/**
 * Parsed dates of one file, as day numbers. Dates repeat a lot in real files,
 * so each distinct value is parsed only once. Created per read, never shared.
 */
final class DateCache
{
    /** Kept small; when full, the cache simply starts over. */
    public const int MAX_ENTRIES = 5000;

    /** @var array<string, int|false> Raw value => day number, or false if invalid. */
    private array $days = [];

    private readonly int $today;

    public function __construct(
        private readonly DateParser $parser,
    ) {
        $this->today = DayNumber::fromDate(CarbonImmutable::today());
    }

    public function isNull(string $value): bool
    {
        return $this->parser->isNull($value);
    }

    /**
     * @throws InvalidDateException
     */
    public function dayNumber(string $value): int
    {
        if (! array_key_exists($value, $this->days)) {
            if (count($this->days) >= self::MAX_ENTRIES) {
                $this->days = [];
            }

            try {
                $this->days[$value] = $this->parser->parseDayNumber($value);
            } catch (InvalidDateException) {
                $this->days[$value] = false;
            }
        }

        return $this->days[$value] === false ? throw InvalidDateException::for(trim($value)) : $this->days[$value];
    }

    /**
     * @throws InvalidDateException
     */
    public function dayNumberOrToday(string $value): int
    {
        return $this->isNull($value) ? $this->today : $this->dayNumber($value);
    }
}
