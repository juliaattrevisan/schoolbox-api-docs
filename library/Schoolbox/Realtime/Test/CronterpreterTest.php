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
    /**
     * Test that running Cronterpreter->interpret() returns
     * the expected output for each crontab in the test data set.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTestInterpretations')]
    public function testInterpretations($timeFormat, $weekendDays, $crontab, $expected): void
    {
        $cronterpreter = new Cronterpreter($timeFormat, $weekendDays);

        $this->assertSame(
            $cronterpreter->interpret($crontab),
            $expected,
            "Testing timeFormat '$timeFormat', weekendDays '$weekendDays', crontab '$crontab'."
        );
    }

    /**
     * @return array[]
     */
    public static function providerTestInterpretations(): array
    {
        return [
            // Crontab format: 'minute hour dayOfMonth month dayOfWeek'.
            // Each test data array below has: [timeFormat, weekendDays, crontab, expectedOutput]
            ['g:ia' , '0,6'  , '0 0 * * 0,1,2,3,4,5,6'  , 'weekends and weekdays at 12:00am'],
            ['g:ia' , '0,6'  , '0 0 * * 1,2,3,4,5'      , "weekdays at 12:00am"],
            ['g:ia' , '0,6'  , '0 0 * * 0,1,2,3,4,5'    , "weekdays and Sundays at 12:00am"],
            ['g:ia' , '0,6'  , '0 0 * * 1,2,3,4,5,6'    , "weekdays and Saturdays at 12:00am"],
            ['g:ia' , '0,6'  , '0 0 * * 0,6'            , "weekends at 12:00am"],
            ['g:ia' , '0,6'  , '0 0 * * 0,1,6'          , "weekends and Mondays at 12:00am"],
            ['g:ia' , '0,6'  , '0 0 * * 0,1,2,6'        , "weekends, Mondays and Tuesdays at 12:00am"],
            ['g:ia' , '0,6'  , '0 0 * * 0,1,2,3,6'      , "weekends, Mondays, Tuesdays and Wednesdays at 12:00am"],
            ['g:ia' , '0,6'  , '0 0 * * 0,1,2,3,4,6'    , "weekends, Mondays, Tuesdays, Wednesdays and Thursdays at 12:00am"],
            ['g:ia' , '0,6'  , '0 0 * * 1'              , "Mondays at 12:00am"],
            ['g:ia' , '0,6'  , '0 0 * * 1,4'            , "Mondays and Thursdays at 12:00am"],
            ['g:ia' , '0,6'  , '0 0 * * 0,1,4'          , "Sundays, Mondays and Thursdays at 12:00am"],
            ['g:ia' , '0,6'  , '0 0 * * 6,1,4'          , "Saturdays, Mondays and Thursdays at 12:00am"],
            ['g:i a', '6,0,1', '54 22 * * 0,1,2,3,4,5,6', "weekends and weekdays at 10:54 pm"],
            ['g:i a', '6,0,1', '54 22 * * 1,2,3,4,5'    , "weekdays and Mondays at 10:54 pm"],
            ['g:i a', '6,0,1', '54 22 * * 0,1,2,3,4,5'  , "weekdays, Sundays and Mondays at 10:54 pm"],
            ['g:i a', '6,0,1', '54 22 * * 1,2,3,4,5,6'  , "weekdays, Mondays and Saturdays at 10:54 pm"],
            ['g:i a', '6,0,1', '54 22 * * 0,6'          , "Sundays and Saturdays at 10:54 pm"],
            ['g:i a', '6,0,1', '54 22 * * 0,1,6'        , "weekends at 10:54 pm"],
            ['g:i a', '6,0,1', '04 02 * * 0,1,2,6'      , "weekends and Tuesdays at 2:04 am"],
            ['g:i a', '6,0,1', '04 02 * * 0,1,2,3,6'    , "weekends, Tuesdays and Wednesdays at 2:04 am"],
            ['g:i a', '6,0,1', '04 02 * * 0,1,2,3,4,6'  , "weekends, Tuesdays, Wednesdays and Thursdays at 2:04 am"],
            ['g:i a', '6,0,1', '04 02 * * 1'            , "Mondays at 2:04 am"],
            ['g:i a', '6,0,1', '04 02 * * 1,4'          , "Mondays and Thursdays at 2:04 am"],
            ['g:i a', '6,0,1', '04 02 * * 0,1,4'        , "Sundays, Mondays and Thursdays at 2:04 am"],
            ['g:i a', '6,0,1', '04 02 * * 6,1,4'        , "Saturdays, Mondays and Thursdays at 2:04 am"],
        ];
    }
}
