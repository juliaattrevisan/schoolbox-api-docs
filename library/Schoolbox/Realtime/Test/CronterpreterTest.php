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
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Realtime\Cronterpreter;

class CronterpreterTest extends TestCase
{
    private $inputOutputPairs = [
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
    ];

    /**
     * Set up a mock config object.
     */
    public function setup(): void
    {
        $mockConfig = $this->createMock(ConfigLoader::class);
        $mockConfig
            ->expects($this->any())
            ->method('get')
            ->will(
                $this->returnValueMap(
                    [
                        ['date_time', 'g:ia'],
                        ['locale_weekend', '0,6'],
                    ]
                )
            );
        $this->dateLocale = new Alaress_Date_Locale_Config();
        $this->dateLocale->setConfig($mockConfig);
    }

    /**
     * Test that running Cronterpreter->convertDayIndexesToText() returns
     * the expected output for each input in $this->inputOutputPairs.
     */
    public function testInterpretations()
    {
        $cronterpreter = new Cronterpreter(new Alaress_Date_Locale_Config());
        foreach ($this->inputOutputPairs as $pair) {
            $this->assertSame($cronterpreter->convertDayIndexesToText($pair['input']), $pair['output']);
        }
    }
}
?>
