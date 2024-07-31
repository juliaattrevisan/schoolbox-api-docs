<?php

/**
 * CronterpreterTest
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Realtime\Cronterpreter;

class CronterpreterTest extends TestCase
{
    private array $testDataSets = [
        [
            // Tests for standard weekend [Sunday, Saturday] - implies weekdays are [Monday, Tuesday, Wednesday, Thursday, Friday].
            'weekendDayIndexes' => [0, 6],
            'timeFormat' => 'g:ia',
            'data' => [
                [
                    // Crontab format is: 'minute hour dayOfMonth month dayOfWeek'.
                    'crontab' => '0 0 * * 0,1,2,3,4,5,6',
                    'output' => "weekends and weekdays at 12:00am",
                ],
                [
                    'crontab' => '0 0 * * 1,2,3,4,5',
                    'output' => "weekdays at 12:00am",
                ],
                [
                    'crontab' => '0 0 * * 0,1,2,3,4,5',
                    'output' => "weekdays and Sundays at 12:00am",
                ],
                [
                    'crontab' => '0 0 * * 1,2,3,4,5,6',
                    'output' => "weekdays and Saturdays at 12:00am",
                ],
                [
                    'crontab' => '0 0 * * 0,6',
                    'output' => "weekends at 12:00am",
                ],
                [
                    'crontab' => '0 0 * * 0,1,6',
                    'output' => "weekends and Mondays at 12:00am",
                ],
                [
                    'crontab' => '0 0 * * 0,1,2,6',
                    'output' => "weekends, Mondays and Tuesdays at 12:00am",
                ],
                [
                    'crontab' => '0 0 * * 0,1,2,3,6',
                    'output' => "weekends, Mondays, Tuesdays and Wednesdays at 12:00am",
                ],
                [
                    'crontab' => '0 0 * * 0,1,2,3,4,6',
                    'output' => "weekends, Mondays, Tuesdays, Wednesdays and Thursdays at 12:00am",
                ],
                [
                    'crontab' => '0 0 * * 1',
                    'output' => "Mondays at 12:00am",
                ],
                [
                    'crontab' => '0 0 * * 1,4',
                    'output' => "Mondays and Thursdays at 12:00am",
                ],
                [
                    'crontab' => '0 0 * * 0,1,4',
                    'output' => "Sundays, Mondays and Thursdays at 12:00am",
                ],
                [
                    'crontab' => '0 0 * * 6,1,4',
                    'output' => "Saturdays, Mondays and Thursdays at 12:00am",
                ],
            ],
        ],
        [
             // Tests for custom weekend [Saturday, Sunday, Monday] - implies weekdays are [Tuesday, Wednesday, Thursday, Friday].
            'weekendDayIndexes' => [6, 0, 1],
             'timeFormat' => 'g:i a',
            'data' => [
                [
                    // Crontab format is: 'minute hour dayOfMonth month dayOfWeek'.
                    'crontab' => '54 22 * * 0,1,2,3,4,5,6',
                    'output' => "weekends and weekdays at 10:54 pm",
                ],
                [
                    'crontab' => '54 22 * * 1,2,3,4,5',
                    'output' => "weekdays and Mondays at 10:54 pm",
                ],
                [
                    'crontab' => '54 22 * * 0,1,2,3,4,5',
                    'output' => "weekdays, Sundays and Mondays at 10:54 pm",
                ],
                [
                    'crontab' => '54 22 * * 1,2,3,4,5,6',
                    'output' => "weekdays, Mondays and Saturdays at 10:54 pm",
                ],
                [
                    'crontab' => '54 22 * * 0,6',
                    'output' => "Sundays and Saturdays at 10:54 pm",
                ],
                [
                    'crontab' => '54 22 * * 0,1,6',
                    'output' => "weekends at 10:54 pm",
                ],
                [
                    'crontab' => '04 02 * * 0,1,2,6',
                    'output' => "weekends and Tuesdays at 2:04 am",
                ],
                [
                    'crontab' => '04 02 * * 0,1,2,3,6',
                    'output' => "weekends, Tuesdays and Wednesdays at 2:04 am",
                ],
                [
                    'crontab' => '04 02 * * 0,1,2,3,4,6',
                    'output' => "weekends, Tuesdays, Wednesdays and Thursdays at 2:04 am",
                ],
                [
                    'crontab' => '04 02 * * 1',
                    'output' => "Mondays at 2:04 am",
                ],
                [
                    'crontab' => '04 02 * * 1,4',
                    'output' => "Mondays and Thursdays at 2:04 am",
                ],
                [
                    'crontab' => '04 02 * * 0,1,4',
                    'output' => "Sundays, Mondays and Thursdays at 2:04 am",
                ],
                [
                    'crontab' => '04 02 * * 6,1,4',
                    'output' => "Saturdays, Mondays and Thursdays at 2:04 am",
                ],
            ]
        ]
    ];

    /**
     * Test that running Cronterpreter->interpret() returns
     * the expected output for each crontab in the test data set.
     */
    public function testInterpretations(): void
    {
        $cronterpreter = new Cronterpreter('g:ia', [0,6]);

        foreach ($this->testDataSets as $testDataSet) {
            $cronterpreter->setWeekendDayIndexes($testDataSet['weekendDayIndexes']);
            $cronterpreter->setTimeFormat($testDataSet['timeFormat']);

            foreach ($testDataSet['data'] as $pair) {
                $this->assertSame(
                    $cronterpreter->interpret($pair['crontab']),
                    $pair['output'],
                    "Testing crontab '{$pair['crontab']}'."
                );
            }
        }
    }
}
