<?php

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Pastoral\ReportBuilder\Test;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\Pastoral\AccessLevelCensor;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\ReportBuilder\Helper;
use Schoolbox\Pastoral\ReportBuilder\TabType\StudentByMonth;
use Schoolbox\Pastoral\ReportBuilder\TabType\StudentByWeek;
use Schoolbox\User;
use DateTimeZone;

class helperMethodsTest extends TestCase
{
    private ArrayLoader $configLoader;

    /**
     * Setup method to initialize common dependencies.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->configLoader = new ArrayLoader(
            [
                'date_local' => 'd/m/Y',
                'calendar_start_day' => 1, // Monday as week start
            ]
        );
    }

    /**
     * Test the getMonthsInRange method of the Helper class.
     *
     * @param mixed $startDate
     * @param mixed $endDate
     * @param int   $expectedMonthCount
     * @param array $expectedMonths
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMonthsInRange')]
    public function testMonthsInRange(
        $startDate,
        $endDate,
        $expectedMonthCount,
        $expectedMonths
    ) {
        $helper = new Helper(
            [],
            $this->createMock(PastoralMapper::class),
            $this->configLoader,
            new User(),
            $this->createMock(AccessLevelCensor::class),
        );
        $months = $helper->getMonthsInRange(
            $startDate,
            $endDate,
            StudentByMonth::COLUMN_DATE_FORMAT
        );

        // Validate the count of months
        $this->assertEquals($expectedMonthCount, $months->count());

        // Validate the actual month values
        $this->assertEquals($expectedMonths, $months->getAll());
    }

    /**
     * Data provider for testMonthsInRange.
     *
     * @return array[]
     */
    public static function providerMonthsInRange(): array
    {
        /* Arbitrary fixed 'now' date for consistent testing
         */
        $now = Alaress_Date_Format::newDatabaseDate(
            '2025-03-15 12:00:00',
            new DateTimeZone('UTC')
        );

        return [
            'Case 1: Past Fortnight' => [
                (clone $now)->addWeeks(-2),
                (clone $now),
                1,
                [
                    'mar_2025'
                ],
            ],
            'Case 2: Past 3 Months' => [
                (clone $now)->addMonths(-3),
                (clone $now),
                4,
                [
                    'dec_2024',
                    'jan_2025',
                    'feb_2025',
                    'mar_2025',
                ],
            ],
            /* The year up until the current month
             */
            'Case 3: This Year' => [
                (clone $now)->startOfYear(),
                (clone $now)->endOfYear(),
                10,
                [
                    'jan_2025',
                    'feb_2025',
                    'mar_2025',
                    'apr_2025',
                    'may_2025',
                    'jun_2025',
                    'jul_2025',
                    'aug_2025',
                    'sep_2025',
                    'oct_2025',
                ]
            ],
            'Case 4: Single Month (Full)' => [
                Alaress_Date_Format::newDatabaseDate('2025-01-01 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2025-01-31 23:59:59', new DateTimeZone('UTC')),
                1,
                [
                    'jan_2025',
                ],
            ],
            'Case 5: Two Consecutive Months' => [
                Alaress_Date_Format::newDatabaseDate('2025-01-15 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2025-02-28 23:59:59', new DateTimeZone('UTC')),
                2,
                [
                    'jan_2025',
                    'feb_2025',
                ],
            ],
            'Case 6: Cross Year Boundary (6 months)' => [
                Alaress_Date_Format::newDatabaseDate('2024-10-01 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2025-03-31 23:59:59', new DateTimeZone('UTC')),
                6,
                [
                    'oct_2024',
                    'nov_2024',
                    'dec_2024',
                    'jan_2025',
                    'feb_2025',
                    'mar_2025',
                ],
            ],
            'Case 7: Full Calendar Year (Past Year)' => [
                Alaress_Date_Format::newDatabaseDate('2024-01-01 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2024-12-31 23:59:59', new DateTimeZone('UTC')),
                12,
                [
                    'jan_2024',
                    'feb_2024',
                    'mar_2024',
                    'apr_2024',
                    'may_2024',
                    'jun_2024',
                    'jul_2024',
                    'aug_2024',
                    'sep_2024',
                    'oct_2024',
                    'nov_2024',
                    'dec_2024',
                ],
            ],
            'Case 8: Null Start Date' => [
                null,
                (clone $now),
                0,
                [],
            ],
            'Case 9: Null End Date' => [
                (clone $now)->addMonths(-2),
                null,
                0,
                [],
            ],
            'Case 10: Both Dates Null' => [
                null,
                null,
                0,
                [],
            ],
            'Case 11: Same Month (Different Days)' => [
                Alaress_Date_Format::newDatabaseDate('2025-03-05 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2025-03-25 23:59:59', new DateTimeZone('UTC')),
                1,
                [
                    'mar_2025',
                ],
            ],
            'Case 12: Six Months Range' => [
                Alaress_Date_Format::newDatabaseDate('2024-08-01 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2025-01-31 23:59:59', new DateTimeZone('UTC')),
                6,
                [
                    'aug_2024',
                    'sep_2024',
                    'oct_2024',
                    'nov_2024',
                    'dec_2024',
                    'jan_2025',
                ],
            ],
            'Case 13: One Month in Past' => [
                (clone $now)->addMonths(-1)->startOfMonth(),
                (clone $now)->addMonths(-1)->endOfMonth(),
                1,
                [
                    'feb_2025',
                ],
            ],
            'Case 14: Past 12 Months' => [
                (clone $now)->addMonths(-12),
                (clone $now),
                13,
                [
                    'mar_2024',
                    'apr_2024',
                    'may_2024',
                    'jun_2024',
                    'jul_2024',
                    'aug_2024',
                    'sep_2024',
                    'oct_2024',
                    'nov_2024',
                    'dec_2024',
                    'jan_2025',
                    'feb_2025',
                    'mar_2025',
                ],
            ],
            'Case 15: February Leap Year' => [
                Alaress_Date_Format::newDatabaseDate('2024-02-01 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2024-02-29 23:59:59', new DateTimeZone('UTC')),
                1,
                [
                    'feb_2024',
                ],
            ],
            'Case 16: Quarter (Q1)' => [
                Alaress_Date_Format::newDatabaseDate('2025-01-01 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2025-03-31 23:59:59', new DateTimeZone('UTC')),
                3,
                [
                    'jan_2025',
                    'feb_2025',
                    'mar_2025',
                ],
            ],
            'Case 17: Academic Year (Jul-Jun)' => [
                Alaress_Date_Format::newDatabaseDate('2024-07-01 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2025-06-30 23:59:59', new DateTimeZone('UTC')),
                12,
                [
                    'jul_2024',
                    'aug_2024',
                    'sep_2024',
                    'oct_2024',
                    'nov_2024',
                    'dec_2024',
                    'jan_2025',
                    'feb_2025',
                    'mar_2025',
                    'apr_2025',
                    'may_2025',
                    'jun_2025',
                ],
            ],
        ];
    }

    /**
     * @param $startDate
     * @param $endDate
     * @param $expectedWeekCount
     * @param $validateLastWeek
     * @param $validateFirstWeek
     * @return void
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerWeeksInRange')]
    public function testWeeksInRange(
        $startDate,
        $endDate,
        $expectedWeekCount,
        $validateLastWeek = null,
        $validateFirstWeek = null
    ) {
        $helper = new Helper(
            [],
            $this->createMock(PastoralMapper::class),
            $this->configLoader,
            new User(),
            $this->createMock(AccessLevelCensor::class),
        );
        $weeks = $helper->getWeeksInRange(
            $startDate,
            $endDate,
            StudentByWeek::COLUMN_QUERY_KEY,
        );

        $this->assertEquals($expectedWeekCount, $weeks->count());

        // Validate structure of first and last week if provided
        if (null !== $validateFirstWeek && $weeks->count() > 0) {
            $firstWeek = $weeks->first();
            $this->assertArrayHasKey('start', $firstWeek);
            $this->assertArrayHasKey('end', $firstWeek);
            $this->assertArrayHasKey('key', $firstWeek);
            $this->assertArrayHasKey('label', $firstWeek);

            if (isset($validateFirstWeek['key'])) {
                $this->assertEquals($validateFirstWeek['key'], $firstWeek['key']);
            }
            if (isset($validateFirstWeek['label'])) {
                $this->assertEquals($validateFirstWeek['label'], $firstWeek['label']);
            }
        }

        if (null !== $validateLastWeek && $weeks->count() > 0) {
            $lastWeek = $weeks->last();
            if (isset($validateLastWeek['key'])) {
                $this->assertEquals($validateLastWeek['key'], $lastWeek['key']);
            }
            if (isset($validateLastWeek['label'])) {
                $this->assertEquals($validateLastWeek['label'], $lastWeek['label']);
            }
        }
    }

    /**
     * Data provider for testWeeksInRange.
     *
     * @return array[]
     */
    public static function providerWeeksInRange(): array
    {
        /* Arbitrary fixed 'now' date for consistent testing
         */
        $now = Alaress_Date_Format::newDatabaseDate(
            '2025-03-15 12:00:00',
            new DateTimeZone('UTC')
        );

        return [
            'Case 1: Past Fortnight (2 weeks)' => [
                (clone $now)->addWeeks(-2),
                (clone $now),
                3, // Should include partial weeks
                ['key' => 'week_0'],
                ['key' => 'week_2'],
            ],
            'Case 2: Past Month (approximately 4-5 weeks)' => [
                (clone $now)->addMonths(-1),
                (clone $now),
                5,
                ['key' => 'week_0'],
                ['key' => 'week_4'],
            ],
            'Case 3: Past 3 Months' => [
                (clone $now)->addMonths(-3),
                (clone $now),
                13, // Approximately 13 weeks in 3 months
                ['key' => 'week_0'],
                ['key' => 'week_12'],
            ],
            'Case 4: Single Week' => [
                (clone $now)->addWeeks(-1)->startOfWeek(), // Start of last week (Sunday)
                (clone $now)->addWeeks(-1)->endOfWeek(),   // End of last week (Saturday)
                1,
                ['key' => 'week_0'],
                ['key' => 'week_0'],
            ],
            'Case 5: Current Week Only' => [
                (clone $now)->startOfWeek(),
                (clone $now),
                1,
                ['key' => 'week_0'],
                ['key' => 'week_0'],
            ],
            'Case 6: Null Start Date' => [
                null,
                (clone $now),
                0, // Should return empty collection
                null,
                null,
            ],
            'Case 7: Null End Date' => [
                (clone $now)->addWeeks(-2),
                null,
                0, // Should return empty collection
                null,
                null,
            ],
            'Case 8: Both Dates Null' => [
                null,
                null,
                0, // Should return empty collection
                null,
                null,
            ],
            'Case 9: Year to Date' => [
                (clone $now)->startOfYear(),
                (clone $now),
                11, // From Jan 1 to Mar 15, 2025
                ['key' => 'week_0'],
                ['key' => 'week_10'],
            ],
        ];
    }
}
