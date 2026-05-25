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
    private DateTimeZone $tz;

    protected function setUp(): void
    {
        $this->tz = new DateTimeZone('Australia/Melbourne');
    }

    public function testEmitsOneOccurrencePerInSessionDayMatchingAClassPeriod(): void
    {
        $from = Alaress_Date_Format::newDatabaseDate('2026-04-13 00:00:00', $this->tz);
        $to   = Alaress_Date_Format::newDatabaseDate('2026-04-13 23:59:59', $this->tz);

        $periods = [
            'p1' => ['name' => 'Period 1', 'start_time' => '09:00:00', 'end_time' => '09:50:00'],
        ];
        $days = [
            'd1' => [
                'date' => '2026-04-13',
                'flags' => ['in_session'],
                'period_ids' => ['p1'],
            ],
        ];

        $occ = Classes::expandOccurrencesForTest($from, $to, ['p1'], $periods, $days, $this->tz);

        $this->assertCount(1, $occ);
        $this->assertSame('2026-04-13 09:00:00', $occ[0]['start_at']->getDatabase());
        $this->assertSame('2026-04-13 09:50:00', $occ[0]['finish_at']->getDatabase());
        $this->assertSame('p1', $occ[0]['period_id']);
        $this->assertSame('Period 1', $occ[0]['period_name']);
        $this->assertSame('2026-04-13', $occ[0]['date']);
    }

    public function testSkipsDaysWithoutInSessionFlag(): void
    {
        $from = Alaress_Date_Format::newDatabaseDate('2026-04-11 00:00:00', $this->tz);
        $to   = Alaress_Date_Format::newDatabaseDate('2026-04-13 23:59:59', $this->tz);

        $periods = [
            'p1' => ['name' => 'Period 1', 'start_time' => '09:00:00', 'end_time' => '09:50:00'],
        ];
        $days = [
            'd1' => ['date' => '2026-04-11', 'flags' => ['weekend'], 'period_ids' => []],
            'd2' => ['date' => '2026-04-12', 'flags' => ['weekend'], 'period_ids' => []],
            'd3' => ['date' => '2026-04-13', 'flags' => ['in_session'], 'period_ids' => ['p1']],
        ];

        $occ = Classes::expandOccurrencesForTest($from, $to, ['p1'], $periods, $days, $this->tz);

        $this->assertCount(1, $occ);
        $this->assertSame('2026-04-13 09:00:00', $occ[0]['start_at']->getDatabase());
    }

    public function testEmitsOnlyIntersectionOfClassAndDayPeriods(): void
    {
        $from = Alaress_Date_Format::newDatabaseDate('2026-04-13 00:00:00', $this->tz);
        $to   = Alaress_Date_Format::newDatabaseDate('2026-04-13 23:59:59', $this->tz);

        $periods = [
            'p1' => ['name' => 'Period 1', 'start_time' => '09:00:00', 'end_time' => '09:50:00'],
            'p2' => ['name' => 'Period 2', 'start_time' => '10:00:00', 'end_time' => '10:50:00'],
            'p3' => ['name' => 'Period 3', 'start_time' => '11:00:00', 'end_time' => '11:50:00'],
        ];
        $days = [
            'd1' => [
                'date' => '2026-04-13',
                'flags' => ['in_session'],
                'period_ids' => ['p1', 'p3'],
            ],
        ];

        $occ = Classes::expandOccurrencesForTest(
            $from,
            $to,
            ['p1', 'p2'],
            $periods,
            $days,
            $this->tz
        );

        $this->assertCount(1, $occ);
        $this->assertSame('p1', $occ[0]['period_id']);
    }

    public function testIgnoresDaysOutsideWindow(): void
    {
        $from = Alaress_Date_Format::newDatabaseDate('2026-04-13 00:00:00', $this->tz);
        $to   = Alaress_Date_Format::newDatabaseDate('2026-04-13 23:59:59', $this->tz);

        $periods = [
            'p1' => ['name' => 'Period 1', 'start_time' => '09:00:00', 'end_time' => '09:50:00'],
        ];
        $days = [
            'before' => ['date' => '2026-04-12', 'flags' => ['in_session'], 'period_ids' => ['p1']],
            'on'     => ['date' => '2026-04-13', 'flags' => ['in_session'], 'period_ids' => ['p1']],
            'after'  => ['date' => '2026-04-14', 'flags' => ['in_session'], 'period_ids' => ['p1']],
        ];

        $occ = Classes::expandOccurrencesForTest($from, $to, ['p1'], $periods, $days, $this->tz);

        $this->assertCount(1, $occ);
        $this->assertSame('2026-04-13', $occ[0]['date']);
    }

    public function testSortsOccurrencesChronologically(): void
    {
        $from = Alaress_Date_Format::newDatabaseDate('2026-04-13 00:00:00', $this->tz);
        $to   = Alaress_Date_Format::newDatabaseDate('2026-04-14 23:59:59', $this->tz);

        $periods = [
            'p1' => ['name' => 'Period 1', 'start_time' => '09:00:00', 'end_time' => '09:50:00'],
            'p2' => ['name' => 'Period 2', 'start_time' => '10:00:00', 'end_time' => '10:50:00'],
        ];
        $days = [
            // Out of insertion order on purpose.
            'tue' => ['date' => '2026-04-14', 'flags' => ['in_session'], 'period_ids' => ['p1', 'p2']],
            'mon' => ['date' => '2026-04-13', 'flags' => ['in_session'], 'period_ids' => ['p2', 'p1']],
        ];

        $occ = Classes::expandOccurrencesForTest(
            $from,
            $to,
            ['p1', 'p2'],
            $periods,
            $days,
            $this->tz
        );

        $this->assertCount(4, $occ);
        $this->assertSame('2026-04-13 09:00:00', $occ[0]['start_at']->getDatabase());
        $this->assertSame('2026-04-13 10:00:00', $occ[1]['start_at']->getDatabase());
        $this->assertSame('2026-04-14 09:00:00', $occ[2]['start_at']->getDatabase());
        $this->assertSame('2026-04-14 10:00:00', $occ[3]['start_at']->getDatabase());
    }

    public function testSkipsPeriodWithMissingTimes(): void
    {
        $from = Alaress_Date_Format::newDatabaseDate('2026-04-13 00:00:00', $this->tz);
        $to   = Alaress_Date_Format::newDatabaseDate('2026-04-13 23:59:59', $this->tz);

        $periods = [
            'p1' => ['name' => 'Period 1', 'start_time' => null, 'end_time' => '09:50:00'],
        ];
        $days = [
            'd1' => ['date' => '2026-04-13', 'flags' => ['in_session'], 'period_ids' => ['p1']],
        ];

        $occ = Classes::expandOccurrencesForTest($from, $to, ['p1'], $periods, $days, $this->tz);

        $this->assertCount(0, $occ);
    }

    public function testSkipsUnknownPeriodId(): void
    {
        $from = Alaress_Date_Format::newDatabaseDate('2026-04-13 00:00:00', $this->tz);
        $to   = Alaress_Date_Format::newDatabaseDate('2026-04-13 23:59:59', $this->tz);

        $days = [
            'd1' => ['date' => '2026-04-13', 'flags' => ['in_session'], 'period_ids' => ['missing']],
        ];

        $occ = Classes::expandOccurrencesForTest($from, $to, ['missing'], $periods = [], $days, $this->tz);

        $this->assertCount(0, $occ);
    }

    public function testAcceptsIsoDatetimeForDayDate(): void
    {
        $from = Alaress_Date_Format::newDatabaseDate('2026-04-13 00:00:00', $this->tz);
        $to   = Alaress_Date_Format::newDatabaseDate('2026-04-13 23:59:59', $this->tz);

        $periods = [
            'p1' => ['name' => 'Period 1', 'start_time' => '09:00:00', 'end_time' => '09:50:00'],
        ];
        // Edlink returns dates as ISO datetimes with non-zero UTC offsets, e.g. "2023-08-31T05:00:00.000Z".
        // The importer must extract only the date portion in school-local time.
        $days = [
            'd1' => [
                'date' => '2026-04-13T00:00:00.000Z',
                'flags' => ['in_session'],
                'period_ids' => ['p1'],
            ],
        ];

        $occ = Classes::expandOccurrencesForTest($from, $to, ['p1'], $periods, $days, $this->tz);

        $this->assertCount(1, $occ);
        $this->assertSame('2026-04-13 09:00:00', $occ[0]['start_at']->getDatabase());
    }
}
