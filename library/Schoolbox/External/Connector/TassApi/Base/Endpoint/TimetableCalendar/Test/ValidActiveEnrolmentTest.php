<?php

/**
 * ValidActiveEnrolmentTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\TimetableCalendar\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\TimetableCalendar\ValidActiveEnrolment;

/**
 * Class ValidActiveEnrolmentTest
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\TimetableCalendar
 */
class ValidActiveEnrolmentTest extends TestCase
{
    /**
     * @param string $now
     * @param string $from
     * @param string $to
     * @param bool   $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidActiveEnrolment')]
    public function testValidActiveEnrolment(string $now, string $from, string $to, bool $expected)
    {
        $outcome = (new ValidActiveEnrolment(\Alaress_Date_Format::newDatabaseDate($now)))
            ->isSatisfiedBy($from, $to);

        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidActiveEnrolment
     *
     * @return array
     */
    public static function providerValidActiveEnrolment(): array
    {
        return [
            'Active Example 1' => [
                '2021-06-07 00:00:00',   // simulated now
                '2021-01-27 00:00:00.0', // from
                '2021-07-14 00:00:00.0', // to
                true,
            ],

            'Active Example 2' => [
                '2021-01-27 00:00:00',   // simulated now
                '2021-01-27 00:00:00.0', // from
                '2021-07-14 00:00:00.0', // to
                true,
            ],

            'Active Example 3' => [
                '2021-07-14 00:00:00',   // simulated now
                '2021-01-27 00:00:00.0', // from
                '2021-07-14 00:00:00.0', // to
                true,
            ],

            'Active Example 4' => [
                '2021-01-28 00:00:00',   // simulated now
                '2021-01-27 00:00:00.0', // from
                '2021-07-14 00:00:00.0', // to
                true,
            ],

            'Active Example 5' => [
                '2021-07-13 00:00:00',   // simulated now
                '2021-01-27 00:00:00.0', // from
                '2021-07-14 00:00:00.0', // to
                true,
            ],

            'Active Example 6' => [
                '2021-07-13 00:00:00',   // simulated now
                '',                      // from
                '',                      // to
                true,
            ],

            'Inactive Example 1' => [
                '2021-01-26 00:00:00',   // simulated now
                '2021-01-27 00:00:00.0', // from
                '2021-07-14 00:00:00.0', // to
                false,
            ],

            'Inactive Example 2' => [
                '2021-01-02 00:00:00',   // simulated now
                '2021-01-27 00:00:00.0', // from
                '2021-07-14 00:00:00.0', // to
                false,
            ],

            'Inactive Example 3' => [
                '2021-07-15 00:00:00',   // simulated now
                '2021-01-27 00:00:00.0', // from
                '2021-07-14 00:00:00.0', // to
                false,
            ],

            'Inactive Example 4' => [
                '2021-07-25 00:00:00',   // simulated now
                '2021-01-27 00:00:00.0', // from
                '2021-07-14 00:00:00.0', // to
                false,
            ],

            /* Exotic usages
             */
            'Exotic Example 1' => [
                '2021-07-25 00:00:00',   // simulated now
                '',                      // from
                '2021-07-14 00:00:00.0', // to
                true,
            ],

            'Exotic Example 2' => [
                '2021-07-25 00:00:00',   // simulated now
                '2021-01-27 00:00:00.0', // from
                '',                      // to
                true,
            ],
        ];
    }
}
