<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\DateOrder;
use App\Services\DateNames;
use App\Services\DateParser;

trait CreatesDateParser
{
    private static ?DateNames $dateNames = null;

    /**
     * A parser with the month-name languages from config/employees.php, for tests without the app container.
     */
    protected static function dateParser(DateOrder $order = DateOrder::DayFirst): DateParser
    {
        self::$dateNames ??= new DateNames((require __DIR__.'/../../config/employees.php')['month_name_locales']);

        return new DateParser(self::$dateNames, $order);
    }
}
