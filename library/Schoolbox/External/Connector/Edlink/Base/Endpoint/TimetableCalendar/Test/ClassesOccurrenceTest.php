<?php

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint\TimetableCalendar\Test;

use Alaress_Date_Format;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Edlink\Base\Endpoint\TimetableCalendar\Classes;

/**
 * @covers \Schoolbox\External\Connector\Edlink\Base\Endpoint\TimetableCalendar\Classes
 */
class ClassesOccurrenceTest extends TestCase
{
    public function testExpandsMonWedFriPeriodAcrossOneWeek(): void
    {
        $tz = new DateTimeZone('Australia/Melbourne');
        $from = Alaress_Date_Format::newDatabaseDate('2026-04-13 00:00:00', $tz); // Mon
        $to   = Alaress_Date_Format::newDatabaseDate('2026-04-19 23:59:59', $tz); // Sun

        $periods = [
            'p1' => [
                'name' => 'Period 1',
                'start_time' => '09:00:00',
                'end_time' => '09:50:00',
                'days' => ['monday', 'wednesday', 'friday'],
            ],
        ];

        $occurrences = Classes::expandOccurrencesForTest($from, $to, ['p1'], $periods, $tz);

        $this->assertCount(3, $occurrences);

        $this->assertSame('2026-04-13 09:00:00', $occurrences[0]['start_at']->getDatabase());
        $this->assertSame('2026-04-13 09:50:00', $occurrences[0]['finish_at']->getDatabase());
        $this->assertSame('p1', $occurrences[0]['period_id']);
        $this->assertSame('Period 1', $occurrences[0]['period_name']);

        $this->assertSame('2026-04-15 09:00:00', $occurrences[1]['start_at']->getDatabase());
        $this->assertSame('2026-04-17 09:00:00', $occurrences[2]['start_at']->getDatabase());
    }

    public function testSkipsPeriodWithMissingTimes(): void
    {
        $tz = new DateTimeZone('Australia/Melbourne');
        $from = Alaress_Date_Format::newDatabaseDate('2026-04-13 00:00:00', $tz);
        $to   = Alaress_Date_Format::newDatabaseDate('2026-04-13 23:59:59', $tz);

        $periods = [
            'p1' => ['name' => 'Period 1', 'start_time' => null, 'end_time' => '10:00:00', 'days' => ['monday']],
        ];

        $occurrences = Classes::expandOccurrencesForTest($from, $to, ['p1'], $periods, $tz);
        $this->assertCount(0, $occurrences);
    }

    public function testSkipsUnknownPeriodId(): void
    {
        $tz = new DateTimeZone('Australia/Melbourne');
        $from = Alaress_Date_Format::newDatabaseDate('2026-04-13 00:00:00', $tz);
        $to   = Alaress_Date_Format::newDatabaseDate('2026-04-13 23:59:59', $tz);

        $occurrences = Classes::expandOccurrencesForTest($from, $to, ['missing'], [], $tz);
        $this->assertCount(0, $occurrences);
    }

    public function testMultiplePeriodsCombineInChronologicalOrder(): void
    {
        $tz = new DateTimeZone('Australia/Melbourne');
        $from = Alaress_Date_Format::newDatabaseDate('2026-04-13 00:00:00', $tz); // Mon
        $to   = Alaress_Date_Format::newDatabaseDate('2026-04-13 23:59:59', $tz);

        $periods = [
            'p1' => ['name' => 'Period 1', 'start_time' => '09:00:00', 'end_time' => '09:50:00', 'days' => ['monday']],
            'p2' => ['name' => 'Period 2', 'start_time' => '10:00:00', 'end_time' => '10:50:00', 'days' => ['monday']],
        ];

        $occurrences = Classes::expandOccurrencesForTest($from, $to, ['p2', 'p1'], $periods, $tz);
        $this->assertCount(2, $occurrences);
        $this->assertSame('2026-04-13 09:00:00', $occurrences[0]['start_at']->getDatabase());
        $this->assertSame('2026-04-13 10:00:00', $occurrences[1]['start_at']->getDatabase());
    }
}
