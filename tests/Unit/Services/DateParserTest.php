<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\DateOrder;
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
            '2013-Nov-01', 'Fri, 01 Nov 2013 10:00:00 +0000',
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
}
