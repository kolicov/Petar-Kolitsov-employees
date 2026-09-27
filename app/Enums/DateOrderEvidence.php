<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a single value says about the day/month order of numeric dates with the year last.
 */
enum DateOrderEvidence
{
    /** Not such a date (year first, month name, ...), or both readings give the same date. */
    case None;

    /** Both readings are valid and differ, e.g. 01/02/2013. */
    case Ambiguous;

    /** Only day-first is valid, e.g. 25/02/2013. */
    case DayFirst;

    /** Only month-first is valid, e.g. 02/25/2013. */
    case MonthFirst;
}
