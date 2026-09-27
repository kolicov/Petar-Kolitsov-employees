# Employees who worked together the longest

A Laravel application that reads a CSV file of project assignments
(`EmpID, ProjectID, DateFrom, DateTo`) and finds the **pair of employees who worked
together on common projects for the longest time**. Two employees worked together
when they have periods on the same project that overlap; the pair's score is the
sum of those overlapping days across all their common projects.

```
143, 218, 372        <- EmpID1, EmpID2, TotalDaysTogether
```

## Features

- **Core:** CSV input, `DateTo = NULL` means today, result in the `EmpID1, EmpID2, TotalDaysTogether` format.
- **Bonus 1: web UI.** Pick a file (it's analysed as soon as you choose it) to see the winning pair and a
  sortable datagrid of **all their common projects** (`Employee ID #1`, `Employee ID #2`, `Project ID`,
  `Days worked`) with a total row.
- **Bonus 2: date formats.** ISO 8601, numeric dates with `/`, `.` or `-`, two-digit years, month
  names, weekdays, RFC 2822 and Unix timestamps. See the [full list](#supported-date-formats).
- **Console command:** `php artisan employees:longest-pair file.csv [--details]`.
- **Robust CSV reading:** optional header, auto-detected `,` `;` or tab delimiter, UTF-8 BOM,
  UTF-16 (Excel "Unicode text"), CRLF, blank lines, quoted values, streaming. Invalid rows are skipped
  with line-numbered warnings.

## Run with Docker

```bash
docker compose up --build
```

Open <http://localhost:8000>. An app key is generated on first start. No database is used.

## Run locally

Requirements: PHP 8.4+, Composer, Node.js 20+ and npm.

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install && npm run build
php artisan serve
```

Open <http://localhost:8000>. Uploads are also limited by PHP's `upload_max_filesize`
(often 2 MB by default); the app itself accepts files up to 20 MB, which the Docker image allows.

## Console usage

```bash
$ php artisan employees:longest-pair samples/sample.csv
143, 218, 372

$ php artisan employees:longest-pair samples/sample.csv --details
143, 218, 372

+----------------+----------------+------------+-------------+
| Employee ID #1 | Employee ID #2 | Project ID | Days worked |
+----------------+----------------+------------+-------------+
| 143            | 218            | 10         | 214         |
| 143            | 218            | 12         | 36          |
| 143            | 218            | 15         | 122         |
+----------------+----------------+------------+-------------+
| Total          |                |            | 372         |
+----------------+----------------+------------+-------------+
```

- Only the result (and the `--details` table) goes to **stdout**; warnings about skipped rows
  and errors go to **stderr**, so `... 2>/dev/null` prints just the result line.
- Exit code `0` on success (also when no pair overlapped), `1` when the file is missing, unreadable,
  empty or has no valid rows.

## Tests

```bash
php artisan test
./vendor/bin/pint --test
```

Unit tests cover `DateParser`, `CsvEmployeeReader` and `EmployeePairFinder`; feature tests cover the
upload page, validation, the console command and every sample file. "Today" is frozen in tests.

## CSV format and sample files

```
EmpID, ProjectID, DateFrom, DateTo
143, 12, 2013-11-01, 2014-01-05
218, 10, 2012-05-16, NULL
```

| File | What it shows | Result |
|---|---|---|
| `samples/sample.csv` | The assignment rows plus more; the winner has 3 common projects (214 + 36 + 122 days). The runner-up (143, 301) has 365 days on a single project. | `143, 218, 372` |
| `samples/mixed-date-formats.csv` | The same data written in many date formats, quoted values, `NULL`/`null`/empty DateTo | `143, 218, 372` |
| `samples/with-errors.csv` | 7 invalid rows (text ID, missing column, bad/impossible date, DateFrom after DateTo, missing DateFrom), reported as warnings | `1, 2, 17` |
| `samples/no-overlap.csv` | Nobody overlaps | "No pair of employees worked together..." |

The results don't depend on the current date: in these files, no two open-ended (`NULL`) periods share a project.

### Supported date formats

| Kind | Examples |
|---|---|
| ISO 8601 | `2013-11-01`, `2013-11-01T10:00:00`, `2013-11-01 10:00:00`, `2013-11-01T10:00:00+02:00`, `20131101` |
| Year first | `2013/11/01`, `2013.11.01` |
| Day/month first | `01/11/2013`, `11/13/2013`, `01.11.2013`, `01-11-2013`, `1/11/2013` |
| Two-digit years | `01/11/13`, `01.11.13`, `01-Nov-13` (`00`–`69` → 2000s, `70`–`99` → 1900s) |
| Month names | `1 Nov 2013`, `01-Nov-2013`, `Nov 1, 2013`, `November 1, 2013`, `1 November 2013`, `1st November 2013`, `2013-Nov-01` |
| With weekday | `Friday, November 1, 2013`, `Fri, 01 Nov 2013 10:00:00 +0000` (the weekday must match the date) |
| Unix timestamp | `1383264000` (seconds) or `1383264000000` (milliseconds), read as UTC |
| No end date | `NULL`, `null`, empty value → today (DateTo only) |

Only the calendar date is used; times and timezones are ignored. Impossible dates such as `2013-02-30`
are rejected rather than rolled over, and relative expressions (`tomorrow`, `next friday`) or incomplete
ones (`Nov 2013`) are not accepted.

## Assumptions and decisions

- **Inclusive day counting:** both the first and the last day count, so two employees on the same
  project on the same single day worked together for **1 day**. Switch with
  `EmployeePairFinder::COUNT_DAYS_INCLUSIVE`.
- **NULL = today:** `NULL` (any case) or an empty DateTo means today (from Carbon's clock, frozen in
  tests). DateFrom is required.
- **Ambiguous dates are day-first** (European): `01/02/2013` is 1 February. If the first number is > 12
  the date is always day-first, and if the second number is > 12 it is always month-first. Change the
  default with `EMPLOYEES_AMBIGUOUS_DATE_ORDER=month_first` (`config/employees.php`).
- **Repeated periods are merged:** if an employee has several overlapping or back-to-back records on
  the same project, they are merged first, so no day is counted twice. Separate periods (left and came
  back) all count.
- **Tie-break:** when several pairs share the highest total, the pair with the lowest `EmpID1` wins,
  then the lowest `EmpID2`. The UI and the console mention the tie.
- **Pairs are unordered** and always shown with the smaller ID first; an employee is never paired with
  themselves.
- **Invalid rows are skipped** with warnings (`Line 7: invalid date 'abc'`). The file is rejected only
  if it is empty or has no valid rows.
- **The header is optional:** a first row whose EmpID is not a number is treated as the header.
- **No database or storage:** the uploaded temp file is read directly and never saved.

## Architecture

| Class | Responsibility |
|---|---|
| `App\Services\DateParser` | Turns a date string into a date (strict formats, then validated fallbacks). Knows NULL → today. |
| `App\Services\CsvEmployeeReader` | Streams the CSV with `fgetcsv`, detects header/delimiter/BOM, validates rows → `CsvReadResult` (records + warnings). |
| `App\Services\EmployeePairFinder` | Pure calculation (no I/O, no framework): records → `PairResult` or `null`. |
| `App\DTO\*` | Readonly DTOs: `EmployeeRecord`, `ProjectOverlap`, `PairResult`, `CsvReadResult`. |
| `App\Http\Controllers\EmployeePairController` | Thin: validated upload → reader → finder → view. |
| `App\Http\Requests\UploadCsvRequest` | File required, not empty, `.csv`/`.txt`, plain text, max 20 MB. |
| `App\Console\Commands\FindLongestPairCommand` | `employees:longest-pair` console command. |
| `resources/views/employees/*`, `resources/js/app.js` | Blade + Tailwind page; small vanilla JS for auto-submit and column sorting. |

### Algorithm

1. **Group** the periods by project, then by employee (each date is turned into a day number).
2. **Merge** each employee's overlapping or adjacent periods on a project into disjoint periods.
3. For each project, for **every two employees** on it (smaller ID first), add up the overlap of
   their periods: `start = max(from1, from2)`, `end = min(to1, to2)`, and if `start <= end` the overlap
   is `end - start + 1` days.
4. **Accumulate** the days in a map `[empId1][empId2][projectId] => days`, then pick the pair with
   the highest sum (tie-break above). Its per-project entries become the datagrid rows, so the rows
   always add up to the total.

Employees are only compared within a project, so the cost grows with the number of people per
project, not with the size of the whole file.
