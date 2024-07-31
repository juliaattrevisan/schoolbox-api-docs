<?php

/**
 * CronterpreterTest
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Test;

use Alaress_Date_Locale_Config;
use PHPUnit\Framework\TestCase;
use Schoolbox\Realtime\Cronterpreter;

class CronterpreterTest extends TestCase
{
    private array $inputOutputPairsByLocaleWeekend = [
        '0,6' => [ // Tests for standard weekend [Sunday, Saturday].
            [
                'input'  => [0, 1, 2, 3, 4, 5, 6],
                'output' => "weekends and weekdays",
            ],
            [
                'input'  => [1, 2, 3, 4, 5],
                'output' => "weekdays",
            ],
            [
                'input'  => [0, 1, 2, 3, 4, 5],
                'output' => "weekdays and Sundays",
            ],
            [
                'input'  => [1, 2, 3, 4, 5, 6],
                'output' => "weekdays and Saturdays",
            ],
            [
                'input'  => [0, 6],
                'output' => "weekends",
            ],
            [
                'input'  => [0, 1, 6],
                'output' => "weekends and Mondays",
            ],
            [
                'input'  => [0, 1, 2, 6],
                'output' => "weekends, Mondays and Tuesdays",
            ],
            [
                'input'  => [0, 1, 2, 3, 6],
                'output' => "weekends, Mondays, Tuesdays and Wednesdays",
            ],
            [
                'input'  => [0, 1, 2, 3, 4, 6],
                'output' => "weekends, Mondays, Tuesdays, Wednesdays and Thursdays",
            ],
            [
                'input'  => [1],
                'output' => "Mondays",
            ],
            [
                'input'  => [1, 4],
                'output' => "Mondays and Thursdays",
            ],
            [
                'input'  => [0, 1, 4],
                'output' => "Sundays, Mondays and Thursdays",
            ],
            [
                'input'  => [6, 1, 4],
                'output' => "Saturdays, Mondays and Thursdays",
            ],
        ],
        '6,0,1' => [ // Tests for custom weekend [Saturday, Sunday, Monday] => weekdays are [Tuesday, Wednesday, Thursday, Friday].
            [
                'input'  => [0, 1, 2, 3, 4, 5, 6],
                'output' => "weekends and weekdays",
            ],
            [
                'input'  => [1, 2, 3, 4, 5],
                'output' => "weekdays and Mondays",
            ],
            [
                'input'  => [0, 1, 2, 3, 4, 5],
                'output' => "weekdays, Sundays and Mondays",
            ],
            [
                'input'  => [1, 2, 3, 4, 5, 6],
                'output' => "weekdays, Mondays and Saturdays",
            ],
            [
                'input'  => [0, 6],
                'output' => "Sundays and Saturdays",
            ],
            [
                'input'  => [0, 1, 6],
                'output' => "weekends",
            ],
            [
                'input'  => [0, 1, 2, 6],
                'output' => "weekends and Tuesdays",
            ],
            [
                'input'  => [0, 1, 2, 3, 6],
                'output' => "weekends, Tuesdays and Wednesdays",
            ],
            [
                'input'  => [0, 1, 2, 3, 4, 6],
                'output' => "weekends, Tuesdays, Wednesdays and Thursdays",
            ],
            [
                'input'  => [1],
                'output' => "Mondays",
            ],
            [
                'input'  => [1, 4],
                'output' => "Mondays and Thursdays",
            ],
            [
                'input'  => [0, 1, 4],
                'output' => "Sundays, Mondays and Thursdays",
            ],
            [
                'input'  => [6, 1, 4],
                'output' => "Saturdays, Mondays and Thursdays",
            ],
        ]
    ];

    /**
     * Test that running Cronterpreter->convertDayIndexesToText() returns
     * the expected output for each input in $this->inputOutputPairs.
     */
    public function testInterpretations(): void
    {
        $cronterpreter = new Cronterpreter(new Alaress_Date_Locale_Config());

        foreach ($this->inputOutputPairsByLocaleWeekend as $localeWeekend => $inputOutputPairs) {
            $weekendDayIndexes = array_map('intval', explode(',', $localeWeekend));
            $cronterpreter->setWeekendDayIndexes($weekendDayIndexes);

            foreach ($inputOutputPairs as $pair) {
                $this->assertSame($cronterpreter->convertDayIndexesToText($pair['input']), $pair['output']);
            }
        }
    }
}
