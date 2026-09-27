<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Translator;

/**
 * Month and weekday names in the enabled languages, taken from Carbon's own
 * translation files and built once, on first use.
 */
final class DateNames
{
    private const array MONTH_KEYS = ['months', 'months_standalone', 'months_short', 'months_short_standalone'];

    private const array WEEKDAY_KEYS = ['weekdays', 'weekdays_standalone', 'weekdays_short'];

    /** @var array<string, int>|null Name => month (1-12). */
    private ?array $months = null;

    /** @var array<string, int>|null Name => weekday (0 = Sunday, as in Carbon). */
    private ?array $weekdays = null;

    /**
     * @param  list<string>  $locales  Carbon locale codes, e.g. ['en', 'bg', 'de'].
     */
    public function __construct(
        private readonly array $locales,
    ) {}

    public function month(string $word): ?int
    {
        $this->load();

        return $this->months[$word] ?? null;
    }

    public function weekday(string $word): ?int
    {
        $this->load();

        return $this->weekdays[$word] ?? null;
    }

    /**
     * Names are stored the way the parser sees input: lowercase, without a trailing dot.
     */
    public static function normalise(string $name): string
    {
        return rtrim(mb_strtolower(trim($name)), '.');
    }

    private function load(): void
    {
        if ($this->months !== null) {
            return;
        }

        $months = [];
        $weekdays = [];

        foreach ($this->locales as $locale) {
            $messages = Translator::get($locale)->getMessages()[$locale] ?? [];

            foreach (self::MONTH_KEYS as $key) {
                $this->collect($months, $messages[$key] ?? [], 1);
            }

            foreach (self::WEEKDAY_KEYS as $key) {
                $this->collect($weekdays, $messages[$key] ?? [], 0);
            }
        }

        $months = array_filter($months, fn (?int $month): bool => $month !== null);

        // A word that is also a month name (Spanish "mar." for Tuesday vs "Mar" for March) is read as the month.
        $this->weekdays = array_diff_key(array_filter($weekdays, fn (?int $day): bool => $day !== null), $months);
        $this->months = $months;
    }

    /**
     * Adds names to the lookup. A word that means different things in different
     * languages is marked with null, so it can never produce a wrong date.
     *
     * @param  array<string, int|null>  $lookup
     * @param  mixed  $names  A list of 12 month or 7 weekday names.
     */
    private function collect(array &$lookup, mixed $names, int $firstValue): void
    {
        if (! is_array($names)) {
            return;
        }

        foreach (array_values($names) as $index => $name) {
            if (! is_string($name) || ($word = self::normalise($name)) === '') {
                continue;
            }

            $value = $firstValue + $index;
            $lookup[$word] = array_key_exists($word, $lookup) && $lookup[$word] !== $value ? null : $value;
        }
    }
}
