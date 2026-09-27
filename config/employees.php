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

    /*
    |--------------------------------------------------------------------------
    | Languages for month and weekday names
    |--------------------------------------------------------------------------
    |
    | Month and weekday names are recognised in these languages (Carbon locale
    | codes), e.g. "1 ноември 2013" or "1 de noviembre de 2013". A word that
    | means different months in two of these languages is ignored.
    |
    */

    'month_name_locales' => ['en', 'bg', 'de', 'fr', 'es', 'it', 'pt', 'nl', 'ru', 'pl', 'ro', 'tr'],

];
