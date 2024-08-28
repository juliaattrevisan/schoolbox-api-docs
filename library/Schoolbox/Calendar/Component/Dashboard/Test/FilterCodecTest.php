<?php

/**
 * FilterCodecTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Calendar\Component\Dashboard\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Calendar\Component\Dashboard\FilterCodec;
use Scrub;

/**
 * Component Config settings for event types and sources
 */
class FilterCodecTest extends TestCase
{
    /**
     * @param string|null $input
     * @param string $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerFilterCodecEncodeDecode')]
    public function testFilterCodecEncodeDecode(?string $input, string $expected)
    {
        $codec = new FilterCodec();

        $outcome = $codec->encode($codec->decode($codec->encode($codec->decode($input))));

        $this->assertEquals($expected, $outcome);
    }


    /**
     * Test encoding and re-encoding of decoded values
     *
     * @return array
     */
    public static function providerFilterCodecEncodeDecode(): array
    {
        return [
            'cycling re-encode 1' => [
                "eventType:1,2,3,4;source:5,6,7",
                "eventType:1,2,3,4;source:5,6,7"
            ]
        ];
    }


    /**
     * @param string|null $input
     * @param array $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerFilterCodecDecode')]
    public function testFilterCodecDecode(?string $input, array $expected)
    {
        $outcome = (new FilterCodec())->decode($input);

        $this->assertEquals($expected, $outcome);
    }


    /**
     * Data provider for testFilterCodecDecode
     *
     * @return array
     */
    public static function providerFilterCodecDecode(): array
    {
        $codec = new FilterCodec();

        return [
            /* null/empty-string cases are generally initial conditions
             * which cannot be revisited
             */
            'null value' => [
                null,
                $codec->getDefaultPayload()
            ],

            'scrubbed null value' => [
                Scrub::text(null),
                $codec->getDefaultPayload()
            ],

            'empty string value' => [
                '',
                $codec->getDefaultPayload()
            ],

            'scrubbed empty string value' => [
                '',
                $codec->getDefaultPayload()
            ],

            /* Selections
             */
            'No selections' => [
                Scrub::text("eventType:;source:"),
                $codec->getDefaultPayload()
            ],

            'One event type' => [
                Scrub::text("eventType:1;source:"),
                [
                    'eventType' => [1],
                    'source' => []
                ]
            ],

            'Multiple event types' => [
                Scrub::text("eventType:1,2,3,4;source:"),
                [
                    'eventType' => [1,2,3,4],
                    'source' => []
                ]
            ],

            'One source' => [
                Scrub::text("eventType:;source:5"),
                [
                    'eventType' => [],
                    'source' => [5]
                ]
            ],

            'Multiple sources' => [
                Scrub::text("eventType:;source:5,6,7"),
                [
                    'eventType' => [],
                    'source' => [5,6,7]
                ]
            ],

            'Typical usage' => [
                Scrub::text("eventType:1,2,3,4;source:5,6,7"),
                [
                    'eventType' => [1,2,3,4],
                    'source' => [5,6,7]
                ]
            ],
        ];
    }
}
