<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\CsvReadResult;
use App\DTO\EmployeeRecord;
use App\Exceptions\CsvImportException;
use App\Services\CsvEmployeeReader;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tests\Support\CreatesDateParser;

final class CsvEmployeeReaderTest extends TestCase
{
    use CreatesDateParser;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2024-06-15');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        array_map(unlink(...), $this->files);
        parent::tearDown();
    }

    public function testItReadsTheAssignmentSampleWithoutAHeader(): void
    {
        $result = $this->read("143, 12, 2013-11-01, 2014-01-05\n218, 10, 2012-05-16, NULL\n143, 10, 2009-01-01, 2011-04-27\n");

        $this->assertSame([
            [143, 12, '2013-11-01', '2014-01-05'],
            [218, 10, '2012-05-16', '2024-06-15'],
            [143, 10, '2009-01-01', '2011-04-27'],
        ], $this->rows($result));
        $this->assertSame([], $result->warnings);
    }

    public function testRecordsKeepDatesAsDayNumbersNotDateObjects(): void
    {
        $record = $this->read("143, 12, 2013-11-01, NULL\n")->records[0];

        foreach (get_object_vars($record) as $property => $value) {
            $this->assertIsInt($value, $property);
        }
        $this->assertSame(16010, $record->fromDay);
        $this->assertSame('2013-11-01', $record->dateFrom()->format('Y-m-d'));
        $this->assertSame('2024-06-15', $record->dateTo()->format('Y-m-d'));
    }

    public function testAHeaderRowIsSkipped(): void
    {
        $result = $this->read("EmpID, ProjectID, DateFrom, DateTo\n143, 12, 2013-11-01, 2014-01-05\n");

        $this->assertSame([[143, 12, '2013-11-01', '2014-01-05']], $this->rows($result));
        $this->assertSame([], $result->warnings);
    }

    #[TestWith(['EmpID, ProjectID, DateFrom, DateTo'])]
    #[TestWith(['empid,projectid,datefrom,dateto'])]
    #[TestWith(['Employee ID, Project ID, Date From, Date To'])]
    #[TestWith(['EMPID,PROJECTID,DATEFROM,DATETO'])]
    public function testCommonHeaderNamesAreSkippedSilently(string $header): void
    {
        $result = $this->read($header."\n143, 12, 2013-11-01, 2014-01-05\n");

        $this->assertSame([[143, 12, '2013-11-01', '2014-01-05']], $this->rows($result));
        $this->assertSame([], $result->warnings);
    }

    public function testAnInvalidFirstDataRowIsReportedInsteadOfTreatedAsAHeader(): void
    {
        $result = $this->read("abc, 10, 2020-01-01, 2020-02-01\n1, 10, 2020-01-01, 2020-01-10\n2, 10, 2020-01-05, 2020-01-20\n");

        $this->assertSame([[1, 10, '2020-01-01', '2020-01-10'], [2, 10, '2020-01-05', '2020-01-20']], $this->rows($result));
        $this->assertSame(["Line 1: invalid EmpID 'abc'"], $result->warnings);
        $this->assertSame(1, $result->skippedRows);
    }

    public function testBomCrlfBlankLinesAndWhitespaceAreHandled(): void
    {
        $result = $this->read("\xEF\xBB\xBFEmpID,ProjectID,DateFrom,DateTo\r\n\r\n  143 ,\t12 , 2013-11-01 ,  2014-01-05  \r\n   \r\n218,10,2012-05-16,null\r\n\r\n");

        $this->assertSame([
            [143, 12, '2013-11-01', '2014-01-05'],
            [218, 10, '2012-05-16', '2024-06-15'],
        ], $this->rows($result));
        $this->assertSame([], $result->warnings);
    }

    public function testABomBeforeADataRowIsRemoved(): void
    {
        $result = $this->read("\xEF\xBB\xBF143,12,2013-11-01,2014-01-05\n");

        $this->assertSame([[143, 12, '2013-11-01', '2014-01-05']], $this->rows($result));
    }

    public function testSemicolonDelimiterIsDetected(): void
    {
        $result = $this->read("EmpID;ProjectID;DateFrom;DateTo\n143;12;01.11.2013;05.01.2014\n");

        $this->assertSame([[143, 12, '2013-11-01', '2014-01-05']], $this->rows($result));
    }

    public function testTabDelimiterIsDetected(): void
    {
        $result = $this->read("143\t12\t2013-11-01\t2014-01-05\n");

        $this->assertSame([[143, 12, '2013-11-01', '2014-01-05']], $this->rows($result));
    }

    public function testQuotedValuesMayContainTheDelimiter(): void
    {
        $result = $this->read("\"143\",\"12\",\"Nov 1, 2013\",\"January 5, 2014\"\n");

        $this->assertSame([[143, 12, '2013-11-01', '2014-01-05']], $this->rows($result));
    }

    public function testDelimitersInsideQuotedValuesDoNotConfuseDetection(): void
    {
        $result = $this->read("143;12;\"Friday, November 1, 2013\";\"Nov 5, 2013\"\n");

        $this->assertSame([[143, 12, '2013-11-01', '2013-11-05']], $this->rows($result));
    }

    public function testUtf16FilesAreConverted(): void
    {
        $contents = "\u{FEFF}EmpID\tProjectID\tDateFrom\tDateTo\r\n143\t12\t2013-11-01\t2014-01-05\r\nabc\t1\t2\t3\r\n";

        foreach (['UTF-16LE', 'UTF-16BE'] as $encoding) {
            $result = $this->read(mb_convert_encoding($contents, $encoding, 'UTF-8'));

            $this->assertSame([[143, 12, '2013-11-01', '2014-01-05']], $this->rows($result), $encoding);
            $this->assertSame(["Line 3: invalid EmpID 'abc'"], $result->warnings, $encoding);
        }
    }

    public function testLineNumbersStayCorrectAfterAMultiLineQuotedValue(): void
    {
        $result = $this->read("1,2,\"2020-01-01\n\",NULL\nabc,2,2020-01-01,NULL\n");

        $this->assertSame(["Line 3: invalid EmpID 'abc'"], $result->warnings);
    }

    public function testWarningsAreCappedButEverySkippedRowIsCounted(): void
    {
        $result = $this->read("1,1,2020-01-01,NULL\n".str_repeat("x,1,2020-01-01,NULL\n", 600));

        $this->assertSame(600, $result->skippedRows);
        $this->assertCount(501, $result->warnings);
        $this->assertSame('...and 100 more invalid rows.', $result->warnings[500]);
    }

    public function testAnEmptyDateToMeansToday(): void
    {
        $result = $this->read("143,12,2024-06-01,\n");

        $this->assertSame([[143, 12, '2024-06-01', '2024-06-15']], $this->rows($result));
    }

    public function testInvalidRowsAreSkippedWithLineNumbers(): void
    {
        $result = $this->read(implode("\n", [
            'EmpID, ProjectID, DateFrom, DateTo',     // 1
            '1, 100, 2020-01-01, 2020-01-31',         // 2 valid
            'abc, 100, 2020-01-01, 2020-01-10',       // 3
            '3, 100, 2020-01-01',                     // 4
            '',                                       // 5 blank, ignored
            '4, 100, not-a-date, 2020-02-01',         // 6
            '5, 100, 2020-02-30, 2020-03-10',         // 7
            '6, 100, 2020-05-01, 2020-04-01',         // 8
            '7, x12, 2020-01-01, 2020-02-01',         // 9
            '8, 100, NULL, 2020-02-01',               // 10
            '9, 100, 2020-01-01, 2020-02-01, extra',  // 11
            '10, 100, 2020-01-01, 2020-02-01,',       // 12 valid (trailing delimiter)
            '11, 100, 2030-01-01, NULL',              // 13 starts after "today"
        ]));

        $this->assertSame([
            [1, 100, '2020-01-01', '2020-01-31'],
            [10, 100, '2020-01-01', '2020-02-01'],
        ], $this->rows($result));

        $this->assertSame([
            "Line 3: invalid EmpID 'abc'",
            'Line 4: expected 4 columns, found 3',
            "Line 6: invalid date 'not-a-date'",
            "Line 7: invalid date '2020-02-30'",
            'Line 8: DateFrom 2020-05-01 is after DateTo 2020-04-01',
            "Line 9: invalid ProjectID 'x12'",
            'Line 10: DateFrom is missing (only DateTo may be NULL)',
            'Line 11: expected 4 columns, found 5',
            'Line 13: DateFrom 2030-01-01 is after DateTo 2024-06-15',
        ], $result->warnings);
        $this->assertSame(9, $result->skippedRows);
    }

    public function testAnEmptyFileIsAnError(): void
    {
        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('The file is empty or contains only a header row.');

        $this->read('');
    }

    public function testAFileWithOnlyBlankLinesAndAHeaderIsAnError(): void
    {
        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('The file is empty or contains only a header row.');

        $this->read("\n\nEmpID,ProjectID,DateFrom,DateTo\n\n");
    }

    public function testAFileWithoutValidRowsIsAnErrorThatKeepsTheWarnings(): void
    {
        try {
            $this->read("1,2,abc,NULL\n3,4\n");
            $this->fail('Expected a CsvImportException.');
        } catch (CsvImportException $e) {
            $this->assertSame('The file contains no valid rows.', $e->getMessage());
            $this->assertSame(["Line 1: invalid date 'abc'", 'Line 2: expected 4 columns, found 2'], $e->warnings);
        }
    }

    public function testAMissingFileIsAnError(): void
    {
        $this->expectException(CsvImportException::class);
        $this->expectExceptionMessage('does not exist or cannot be read');

        (new CsvEmployeeReader(self::dateParser()))->read('/nonexistent/file.csv');
    }

    private function read(string $contents): CsvReadResult
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return (new CsvEmployeeReader(self::dateParser()))->read($path);
    }

    /**
     * @return list<array{int, int, string, string}>
     */
    private function rows(CsvReadResult $result): array
    {
        return array_map(fn (EmployeeRecord $r): array => [
            $r->empId,
            $r->projectId,
            $r->dateFrom()->format('Y-m-d'),
            $r->dateTo()->format('Y-m-d'),
        ], $result->records);
    }
}
