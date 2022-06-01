<?php

/**
 * ValidActiveTtidTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable\ValidActiveTtid;

/**
 * Class ValidActiveTtidTest
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable
 */
class ValidActiveTtidTest extends TestCase
{

    /**
     * Sampled from an actual school
     */
    private const TTCALENDAR = [
        [
            'CAT_NUM' => '',
            'CMPY_CODE' => '01',
            'TTDATE' => '2022-05-26 00:00:00.0',
            'RECORD_ID' => '47050',
            'LABEL' => 'Thu 26/05/2022',
            'ID' => '39-2022-05-26',
            'TABLE_ID' => 'ttcalendar',
            'VALUE' => '2022-05-26',
            'TT_ID' => '39',
            'DAY_CODE' => '9',
        ],
        [
            'CAT_NUM' => '',
            'CMPY_CODE' => '01',
            'TTDATE' => '2022-05-27 00:00:00.0',
            'RECORD_ID' => '47051',
            'LABEL' => 'Fri 27/05/2022',
            'ID' => '39-2022-05-27',
            'TABLE_ID' => 'ttcalendar',
            'VALUE' => '2022-05-27',
            'TT_ID' => '39',
            'DAY_CODE' => '10',
        ],
        [
            'CAT_NUM' => '',
            'CMPY_CODE' => '01',
            'TTDATE' => '2022-05-28 00:00:00.0',
            'RECORD_ID' => '47052',
            'LABEL' => 'Sat 28/05/2022',
            'ID' => '39-2022-05-28',
            'TABLE_ID' => 'ttcalendar',
            'VALUE' => '2022-05-28',
            'TT_ID' => '39',
            'DAY_CODE' => null,
        ],
        [
            'CAT_NUM' => '',
            'CMPY_CODE' => '01',
            'TTDATE' => '2022-05-29 00:00:00.0',
            'RECORD_ID' => '47053',
            'LABEL' => 'Sun 29/05/2022',
            'ID' => '39-2022-05-29',
            'TABLE_ID' => 'ttcalendar',
            'VALUE' => '2022-05-29',
            'TT_ID' => '39',
            'DAY_CODE' => '',
        ],
        [
            'CAT_NUM' => '',
            'CMPY_CODE' => '01',
            'TTDATE' => '2022-05-30 00:00:00.0',
            'RECORD_ID' => '47054',
            'LABEL' => 'Mon 30/05/2022',
            'ID' => '39-2022-05-30',
            'TABLE_ID' => 'ttcalendar',
            'VALUE' => '2022-05-30',
            'TT_ID' => '39',
            'DAY_CODE' => '1',
        ],
        [
            'CAT_NUM' => '',
            'CMPY_CODE' => '01',
            'TTDATE' => '2022-05-31 00:00:00.0',
            'RECORD_ID' => '47055',
            'LABEL' => 'Tue 31/05/2022',
            'ID' => '39-2022-05-31',
            'TABLE_ID' => 'ttcalendar',
            'VALUE' => '2022-05-31',
            'TT_ID' => '39',
            'DAY_CODE' => '2',
        ],
        [
            'CAT_NUM' => '',
            'CMPY_CODE' => '01',
            'TTDATE' => '2022-06-01 00:00:00.0',
            'RECORD_ID' => '47056',
            'LABEL' => 'Wed 01/06/2022',
            'ID' => '39-2022-06-01',
            'TABLE_ID' => 'ttcalendar',
            'VALUE' => '2022-06-01',
            'TT_ID' => '39',
            'DAY_CODE' => '3',
        ],
    ];


    /**
     * Sampled from an actual school
     */
    private const TTCALENDAR_GAP = [
        [
            'CAT_NUM' => '',
            'CMPY_CODE' => '01',
            'TTDATE' => '2022-05-26 00:00:00.0',
            'RECORD_ID' => '47050',
            'LABEL' => 'Thu 26/05/2022',
            'ID' => '39-2022-05-26',
            'TABLE_ID' => 'ttcalendar',
            'VALUE' => '2022-05-26',
            'TT_ID' => '39',
            'DAY_CODE' => '9',
        ],
        [
            'CAT_NUM' => '',
            'CMPY_CODE' => '01',
            'TTDATE' => '2022-05-27 00:00:00.0',
            'RECORD_ID' => '47051',
            'LABEL' => 'Fri 27/05/2022',
            'ID' => '39-2022-05-27',
            'TABLE_ID' => 'ttcalendar',
            'VALUE' => '2022-05-27',
            'TT_ID' => '39',
            'DAY_CODE' => '10',
        ],
        [
            'CAT_NUM' => '',
            'CMPY_CODE' => '01',
            'TTDATE' => '2022-05-30 00:00:00.0',
            'RECORD_ID' => '47054',
            'LABEL' => 'Mon 30/05/2022',
            'ID' => '39-2022-05-30',
            'TABLE_ID' => 'ttcalendar',
            'VALUE' => '2022-05-30',
            'TT_ID' => '39',
            'DAY_CODE' => '1',
        ],
        [
            'CAT_NUM' => '',
            'CMPY_CODE' => '01',
            'TTDATE' => '2022-05-31 00:00:00.0',
            'RECORD_ID' => '47055',
            'LABEL' => 'Tue 31/05/2022',
            'ID' => '39-2022-05-31',
            'TABLE_ID' => 'ttcalendar',
            'VALUE' => '2022-05-31',
            'TT_ID' => '39',
            'DAY_CODE' => '2',
        ],
        [
            'CAT_NUM' => '',
            'CMPY_CODE' => '01',
            'TTDATE' => '2022-06-01 00:00:00.0',
            'RECORD_ID' => '47056',
            'LABEL' => 'Wed 01/06/2022',
            'ID' => '39-2022-06-01',
            'TABLE_ID' => 'ttcalendar',
            'VALUE' => '2022-06-01',
            'TT_ID' => '39',
            'DAY_CODE' => '3',
        ],
    ];


    /**
     * @dataProvider providerValidActiveTtid
     * @param array  $ttCalendar
     * @param string $date
     * @param bool   $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function testValidActiveTtid(array $ttCalendar, string $date, bool $expected)
    {
        $outcome = (new ValidActiveTtid($ttCalendar))->isActive($date);

        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidActiveEnrolment
     *
     * @return array
     */
    public function providerValidActiveTtid(): array
    {
        return [
            /* These configurations indicate $ttid is active
             */
            'Active Example 1 ($now in the middle of ttCalendar set)' => [
                self::TTCALENDAR,
                '2022-05-29',
                true,
            ],

            'Active Example 2 ($now at the start of ttCalendar set)' => [
                self::TTCALENDAR,
                '2022-05-26',
                true,
            ],

            'Active Example 3 ($now at the end of ttCalendar set)' => [
                self::TTCALENDAR,
                '2022-06-01',
                true,
            ],

            'Active Example 4 (discontiguous ttCalendar set)' => [
                self::TTCALENDAR_GAP,
                '2022-05-28',
                true,
            ],

            /* These configurations indicate $ttid is inactive
             */
            'Inactive Example 1 ($now is outside of ttCalendar set)' => [
                self::TTCALENDAR,
                '2022-01-01',
                false,
            ],

            'Inactive Example 2 ($now is outside of ttCalendar set)' => [
                self::TTCALENDAR,
                '2022-05-25',
                false,
            ],

            'Inactive Example 3 ($now is outside of ttCalendar set)' => [
                self::TTCALENDAR,
                '2022-06-02',
                false,
            ],

            'Inactive Example 4 ($now is outside of ttCalendar set)' => [
                self::TTCALENDAR,
                '2022-10-10',
                false,
            ],

            'Inactive Example 5 (ttCalendar set is empty)' => [
                [],
                '2022-12-01',
                false,
            ],

            'Inactive Example 6 (discontiguous ttCalendar set)' => [
                self::TTCALENDAR_GAP,
                '2022-07-20',
                false,
            ],
        ];
    }
}
