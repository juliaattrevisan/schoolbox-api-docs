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
        [$delay, $jitter, $delayTimestamp]  = (new DelayGen())->getQuitePeriod($input[0], $input[1]);

        if (null === $expected) {
            $this->assertEquals($expected, $delay);
        } else {
            // delay is expected to be a positive integer
            $this->assertGreaterThan($expected, $delay);
        }
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
                [null, null],    // goes into testValidExample() as $input
                null  // goes into testValidExample() as $expected
            ],

            'no start, has end' => [
                [null, '23:59'],
                null
            ],

            'has start, no end' => [
                ['18:00', null],
                null
            ],

            'over midnight' => [
                ['18:00', '06:00'],
                43200 // 12 hours in seconds (should be same or greater than)
            ],

            '1 hr time' => [
                ['18:00', '19:00'],
                3600
            ],
        ];
    }
}
