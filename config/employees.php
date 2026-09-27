<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Ambiguous numeric date order
    |--------------------------------------------------------------------------
    |
    | How to read a date such as "01/02/2013" where both numbers could be the
    | day or the month. "day_first" (European, 1 Feb 2013) or "month_first"
    | (US, 2 Jan 2013). Dates like "13/02/2013" or "02/13/2013" are always
    | resolved automatically, because only one reading is valid.
    |
    */

    'ambiguous_date_order' => env('EMPLOYEES_AMBIGUOUS_DATE_ORDER', 'day_first'),

];
