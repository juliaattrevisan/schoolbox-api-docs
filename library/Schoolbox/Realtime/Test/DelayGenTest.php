<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Schoolbox\Realtime\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Realtime\Mobile\DelayGen;

class DelayGenTest extends TestCase
{
    /**
     * @param mixed $input
     * @param int   $expected
     */
    #[DataProvider('providerValidExample')]
    public function testValidExample($input, $expected)
    {
        [$delay, $jitter, $delayTimestamp]  = (new DelayGen())->getQuitePeriod($input[0], $input[1], $input[2]);

        // check delay is either null (no delay) or a positive integer
        if (null === $expected) {
            $this->assertEquals($expected, $delay);
        } else {
            // delay is expected to be a positive integer
            $this->assertGreaterThan($expected, $delay);
        }

        // check jitter is always positive and less than or equal to BLACKOUT_END_JITTER
        $this->assertGreaterThanOrEqual(0, $jitter);
        $this->assertLessThanOrEqual(DelayGen::BLACKOUT_END_JITTER, $jitter);
    }

    /**
     * Data provider for testValidExample
     *
     * @return array
     */
    public static function providerValidExample(): array
    {
        return [

            /* Add other cases that you expect to be valid
             */
            'No delay' => [
                [null, null, '09:00'],    // goes into testValidExample() as $input
                null  // goes into testValidExample() as $expected
            ],

            'no start, has end' => [
                [null, '23:59', '09:00'],
                null
            ],

            'has start, no end' => [
                ['18:00', null, '09:00'],
                null
            ],

            'over midnight, delay' => [
                ['18:00', '06:00', '19:00'],
                39600 // 11 hours in seconds (should be same or greater than)
            ],

            'over midnight, no delay' => [
                ['18:00', '06:00', '15:00'],
                null
            ],

            '1 hr time' => [
                ['18:00', '19:00', '18:00'],
                3600
            ],

            '1 hr time, half way through' => [
                ['18:00', '19:00', '18:30'],
                1800
            ],

            'everything midnight' => [
                ['00:00', '00:00', '00:00'],
                0
            ],

            'everything midnight, send at 1am' => [
                ['00:00', '00:00', '01:00'],
                null
            ],

            'everything midday' => [
                ['12:00', '12:00', '12:00'],
                0
            ],

            'everything midday, send at 1am' => [
                ['12:00', '12:00', '01:00'],
                null
            ],

            '1 min blackout time' => [
                ['18:00', '18:01', '18:00'],
                1
            ],

            '1 min open time' => [
                ['18:01', '18:00', '17:00'],
                3600
            ],
        ];
    }
}
