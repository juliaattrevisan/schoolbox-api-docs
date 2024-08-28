<?php

/**
 * ValidEventPhotosAvailableTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Geosnapshot\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Geosnapshot\ValidEventPhotosAvailable;

/**
 * Class ValidEventPhotosAvailableTest
 *
 * @package Schoolbox\Example\Test
 */
class ValidEventPhotosAvailableTest extends TestCase
{
    /**
     * @param null|string $apiKey
     * @param bool        $featureFlag
     * @param null|string $eventName
     * @param int         $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validEventPhotosAvailable')]
    public function testValidEventPhotosAvailable($apiKey, $featureFlag, $eventName, $expected)
    {
        $outcome = (new ValidEventPhotosAvailable())
            ->isSatisfiedBy($apiKey, $featureFlag, $eventName);

        /* There are other assertions that you can try (ctrl-click to follow)
         */
        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidEventPhotosAvailable
     *
     * @return array
     */
    public static function provider_validEventPhotosAvailable(): array
    {
        return [

            /* Classical scenario
             * A meaningful search string was provided
             */
            'Meaningful search string 1' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                'Saturday Burpees, April 2021',
                true
            ],

            /* As far as "meaningful" goes, for a compupor
             */
            'Meaningful search string 2' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                'sljfnl*( &(34n 9^ 9ds69124 1904u 01 908u 14',
                true
            ],

            /* Zero-length search string
             */
            'Zero-length search string' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                '',
                false
            ],

            /* Meaningless search string
             * All whitespaces which are trimmed when making request
             */
            'Meaningless search string' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                '        ',
                false
            ],

            /* Null search string
             */
            'Null search string' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                null,
                false
            ],

            /* These cases should fail and deny connectivity
             */
            'API Key is null' => [
                null, // API Key missing
                true, // Feature is enabled in current plan
                'Saturday Burpees, April 2021',
                false
            ],

            'API Key is an empty string' => [
                '', // API Key missing
                true, // Feature is enabled in current plan
                'Saturday Burpees, April 2021',
                false
            ],

            /* The feature flag needs to be enabled
             */
            'Feature flag is enabled' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                'Saturday Burpees, April 2021',
                true
            ],

            'Feature flag is disabled' => [
                'abcd-1234-QWERTY-10000', // API Key set
                false, // Feature is disabled in current plan
                'Saturday Burpees, April 2021',
                false
            ],
        ];
    }
}
