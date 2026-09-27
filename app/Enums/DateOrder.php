<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Preferred reading of an ambiguous numeric date such as "01/02/2013".
 */
enum DateOrder: string
{
    case DayFirst = 'day_first';
    case MonthFirst = 'month_first';
}
