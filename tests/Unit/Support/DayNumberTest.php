<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\DayNumber;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class DayNumberTest extends TestCase
{
    #[TestWith(['1970-01-01', 0])]
    #[TestWith(['2013-11-01', 16010])]
    #[TestWith(['1969-12-31', -1])]
    public function testDatesBecomeDaysSinceTheUnixEpoch(string $date, int $day): void
    {
        $this->assertSame($day, DayNumber::fromDate(new DateTimeImmutable($date)));
        $this->assertSame($date, DayNumber::toDate($day)->format('Y-m-d'));
    }

    public function testTimeOfDayAndTimezoneAreIgnored(): void
    {
        $lateInTokyo = new DateTimeImmutable('2013-11-01 23:59:59', new DateTimeZone('Asia/Tokyo'));
        $earlyInLosAngeles = new DateTimeImmutable('2013-11-01 00:00:01', new DateTimeZone('America/Los_Angeles'));

        $this->assertSame(16010, DayNumber::fromDate($lateInTokyo));
        $this->assertSame(16010, DayNumber::fromDate($earlyInLosAngeles));
    }
}
