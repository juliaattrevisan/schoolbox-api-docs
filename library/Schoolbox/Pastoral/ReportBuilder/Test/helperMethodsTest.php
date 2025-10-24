<?php

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Pastoral\ReportBuilder\Test;

use Alaress_Date_Format;
use Alaress\Authorizer\Authorizer;
use Alaress_Date_Locale_Config;
use Aura\Router\Generator;
use PHPUnit\Framework\TestCase;
use Schoolbox\Collection;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\Pastoral\AccessLevelCensor;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\ReportBuilder\Helper;
use Schoolbox\Pastoral\ReportBuilder\TabType\StudentByMonth;
use Schoolbox\User;
use DateTimeZone;

class helperMethodsTest extends TestCase
{
    private ArrayLoader $configLoader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configLoader = new ArrayLoader(
            [
                'date_local' => 'd/m/Y',
            ]
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerMonthsInRange')]
    public function testMonthsInRange($startDate, $endDate, $expectedMonths)
    {
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

        $this->assertEquals($expectedMonths, $months->getAll());
    }

    /**
     * Data provider for testMonthsInRange.
     *
     * @return array[]
     */
    public static function providerMonthsInRange()
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
                [
                    'mar_2025'
                ],
            ],
            'Case 2: Past 3 Months' => [
                (clone $now)->addMonths(-3),
                (clone $now),
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
        ];
    }


}
