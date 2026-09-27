<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\DateOrder;
use App\Enums\DateOrderEvidence;
use App\Exceptions\InvalidDateException;
use App\Services\DateParser;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tests\Support\CreatesDateParser;

final class DateParserTest extends TestCase
{
    use CreatesDateParser;

    private DateParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2024-06-15 13:45:00');
        $this->parser = self::dateParser();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function formatsOfFirstNovember2013(): array
    {
        $values = [
            // ISO 8601
            '2013-11-01', '2013-11-01T10:00:00', '2013-11-01 10:00:00', '2013-11-01T10:00:00+02:00',
            '2013-11-01T23:59:59.123Z', '20131101',
            // Slashes, dots and dashes (day first by default)
            '2013/11/01', '01/11/2013', '1/11/2013', '01.11.2013', '2013.11.01', '01-11-2013',
            // Two-digit years
            '01/11/13', '01.11.13', '01-11-13',
            // Month names
            '1 Nov 2013', '01-Nov-2013', '01-Nov-13', 'Nov 1, 2013', 'November 1, 2013', '1 November 2013',
            'Friday, November 1, 2013', 'Friday 1 November 2013', '1st November 2013', 'NOV 1, 2013',
            '2013-Nov-01', 'Fri, 01 Nov 2013 10:00:00 +0000', '2013-11-01T10:00:00Z', '2013-11-01T10:00:00.000+02:00',
            '2013-11-1', '1/11/13', '01-NOV-13', 'Nov. 1, 2013', 'November 1st, 2013', '1-November-2013', '1.Nov.2013',
            '01Nov2013', 'Nov-01-2013', 'november 1 2013',
            // Bulgarian
            '1. 11. 2013', '01.11.2013 г.', '01.11.2013г.', '1 ноември 2013', '1 ноември 2013 г.', '01-ное-2013',
            // Other languages
            '1 noviembre 2013', '1 de noviembre de 2013', '1. November 2013', '1 novembre 2013', '1 listopada 2013',
            '1 ноября 2013', 'viernes, 1 de noviembre de 2013', 'петък, 1 ноември 2013',
            // Space-separated numbers and other layouts
            '01 11 2013', '2013 11 01', '01 Nov, 2013', '01/Nov/2013', '2013 Nov 1', '01/Nov/2013:10:00:00 +0000',
            // Unix timestamps (seconds and milliseconds, UTC)
            '1383264000', '1383264000000',
            // Surrounding whitespace
            '  2013-11-01  ',
        ];

        return array_combine($values, array_map(fn (string $value): array => [$value], $values));
    }

    #[DataProvider('formatsOfFirstNovember2013')]
    public function testItParsesSupportedFormats(string $value): void
    {
        $this->assertSame('2013-11-01', $this->parser->parse($value)->toDateString());
    }

    #[TestWith(['Sept 1, 2013', '2013-09-01'])]
    #[TestWith(['1 Sept 2013', '2013-09-01'])]
    #[TestWith(['11/01/2013 10:00 AM', '2013-01-11'])]
    public function testOtherSupportedValues(string $value, string $expected): void
    {
        $this->assertSame($expected, $this->parser->parse($value)->toDateString());
    }

    public function testParseDayNumberMatchesParse(): void
    {
        $this->assertSame(16010, $this->parser->parseDayNumber('1 ноември 2013'));
        $this->assertSame(16010, $this->parser->parseDayNumber('2013-11-01'));
    }

    public function testTheTimeOfDayIsDropped(): void
    {
        $this->assertSame('2013-11-01 00:00:00', $this->parser->parse('2013-11-01T18:30:00')->toDateTimeString());
    }

    #[TestWith(['NULL'])]
    #[TestWith(['null'])]
    #[TestWith(['Null'])]
    #[TestWith([''])]
    #[TestWith(['  '])]
    public function testNullOrEmptyDateToMeansToday(string $value): void
    {
        $this->assertTrue($this->parser->isNull($value));
        $this->assertSame('2024-06-15 00:00:00', $this->parser->parseOrToday($value)->toDateTimeString());
    }

    public function testParseOrTodayStillParsesRealDates(): void
    {
        $this->assertSame('2013-11-01', $this->parser->parseOrToday('2013-11-01')->toDateString());
    }

    #[TestWith(['abc'])]
    #[TestWith(['NULL'])]
    #[TestWith([''])]
    #[TestWith(['tomorrow'])]
    #[TestWith(['next friday'])]
    #[TestWith(['Friday'])]
    #[TestWith(['Nov 2013'])]
    #[TestWith(['2013-11'])]
    #[TestWith(['2013-11-01Tgarbage'])]
    #[TestWith(['2013-11-01 25:99'])]
    #[TestWith(['01/11/2013/5'])]
    #[TestWith(['01/11-2013'])]
    #[TestWith(['123'])]
    #[TestWith(['abc 1 2013'])]
    #[TestWith(['1 foo 2013'])]
    #[TestWith(['1 Nov 2 Dec 2013'])]
    #[TestWith(['1 2013'])]
    #[TestWith(['1 11 2013 5'])]
    #[TestWith(['2013-W44-5'])]
    #[TestWith(['2013-305'])]
    #[TestWith(['2013年11月1日'])]
    public function testInvalidValuesAreRejected(string $value): void
    {
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage(sprintf("invalid date '%s'", $value));

        $this->parser->parse($value);
    }

    #[TestWith(['2013-02-30'])]
    #[TestWith(['30/02/2013'])]
    #[TestWith(['29.02.2015'])]
    #[TestWith(['2013-13-01'])]
    #[TestWith(['13/13/2013'])]
    #[TestWith(['00/01/2013'])]
    #[TestWith(['20130230'])]
    #[TestWith(['30 Feb 2013'])]
    #[TestWith(['Monday, November 1, 2013'])]
    #[TestWith(['31/04/2013'])]
    #[TestWith(['31 ноември 2013'])]
    #[TestWith(['понеделник, 1 ноември 2013'])]
    #[TestWith(['lunes, 1 de noviembre de 2013'])]
    public function testImpossibleDatesAreRejected(string $value): void
    {
        $this->expectException(InvalidDateException::class);

        $this->parser->parse($value);
    }

    public function testLeapDayIsAccepted(): void
    {
        $this->assertSame('2016-02-29', $this->parser->parse('29.02.2016')->toDateString());
    }

    public function testAmbiguousDatesAreDayFirstByDefault(): void
    {
        $this->assertSame('2013-02-01', $this->parser->parse('01/02/2013')->toDateString());
    }

    public function testAmbiguousDatesCanBeMonthFirst(): void
    {
        $parser = self::dateParser(DateOrder::MonthFirst);

        $this->assertSame('2013-01-02', $parser->parse('01/02/2013')->toDateString());
    }

    #[TestWith([DateOrder::DayFirst])]
    #[TestWith([DateOrder::MonthFirst])]
    public function testUnambiguousNumericDatesIgnoreThePreference(DateOrder $order): void
    {
        $parser = self::dateParser($order);

        $this->assertSame('2013-11-13', $parser->parse('13/11/2013')->toDateString(), 'first number > 12 is the day');
        $this->assertSame('2013-11-13', $parser->parse('11/13/2013')->toDateString(), 'second number > 12 is the day');
    }

    #[TestWith(['01/11/13', '2013-11-01'])]
    #[TestWith(['01/11/69', '2069-11-01'])]
    #[TestWith(['01/11/70', '1970-11-01'])]
    #[TestWith(['31.12.99', '1999-12-31'])]
    #[TestWith(['01.01.00', '2000-01-01'])]
    public function testTwoDigitYears(string $value, string $expected): void
    {
        $this->assertSame($expected, $this->parser->parse($value)->toDateString());
    }

    public function testWithAmbiguousOrderReturnsANewParser(): void
    {
        $monthFirst = $this->parser->withAmbiguousOrder(DateOrder::MonthFirst);

        $this->assertNotSame($this->parser, $monthFirst);
        $this->assertSame('2013-02-01', $this->parser->parse('01/02/2013')->toDateString());
        $this->assertSame('2013-01-02', $monthFirst->parse('01/02/2013')->toDateString());
    }

    #[TestWith(['01/02/2013', DateOrderEvidence::Ambiguous])]
    #[TestWith(['01.02.13', DateOrderEvidence::Ambiguous])]
    #[TestWith(['01 02 2013', DateOrderEvidence::Ambiguous])]
    #[TestWith(['01.02.2013 г.', DateOrderEvidence::Ambiguous])]
    #[TestWith(['01/02/2013 10:00', DateOrderEvidence::Ambiguous])]
    #[TestWith(['25/02/2013', DateOrderEvidence::DayFirst])]
    #[TestWith(['25-02-2013', DateOrderEvidence::DayFirst])]
    #[TestWith(['02/25/2013', DateOrderEvidence::MonthFirst])]
    #[TestWith(['12/31/2014', DateOrderEvidence::MonthFirst])]
    #[TestWith(['05/05/2013', DateOrderEvidence::None])]
    #[TestWith(['2013/02/01', DateOrderEvidence::None])]
    #[TestWith(['2013-02-01', DateOrderEvidence::None])]
    #[TestWith(['1 Feb 2013', DateOrderEvidence::None])]
    #[TestWith(['Feb 01 13', DateOrderEvidence::None])]
    #[TestWith(['1383264000', DateOrderEvidence::None])]
    #[TestWith(['NULL', DateOrderEvidence::None])]
    #[TestWith(['abc', DateOrderEvidence::None])]
    public function testDateOrderEvidence(string $value, DateOrderEvidence $expected): void
    {
        $this->assertSame($expected, $this->parser->dateOrderEvidence($value));
    }
}
