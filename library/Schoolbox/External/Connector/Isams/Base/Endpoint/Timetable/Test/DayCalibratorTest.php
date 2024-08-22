<?php

/**
 * DayCalibratorTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Timetable\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Batchxml;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use Schoolbox\External\Connector\Isams\Base\Endpoint\MockFragmentApi;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Timetable\DayCalibrator;

/**
 * Class DayCalibratorTest
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint\Timetable\Test
 */
class DayCalibratorTest extends TestCase
{
    /* These are two xml fragments that we encountered in the wild
     * I've reduced the xml to the relevant parts (the rest aren't used)
     * and modified some of the contents for brevity
     */

    /**
     * ISASC
     */
    private const ISASC_XML = '<iSAMS>
    <TimetableManager>
        <Structure>
            <Week Id="1">
                <Name>Secondary School Week</Name>
                <ShortName>SSW</ShortName>
                <Ordinal>1</Ordinal>
                <Active>1</Active>
                <ShowTimetableWeek>0</ShowTimetableWeek>
                <Days>
                    <Day Id="1">
                        <Name>Monday</Name>
                        <ShortName>Mon</ShortName>
                        <Day>2</Day>
                        <Ordinal>1</Ordinal>
                        <Active>1</Active>
                        <Periods>...</Periods>
                    </Day>
                    <Day Id="2">
                        <Name>Tuesday</Name>
                        <ShortName>Tue</ShortName>
                        <Day>3</Day>
                        <Ordinal>2</Ordinal>
                        <Active>1</Active>
                        <Periods>...</Periods>
                    </Day>
                    <Day Id="3">
                        <Name>Wednesday</Name>
                        <ShortName>Wed</ShortName>
                        <Day>4</Day>
                        <Ordinal>3</Ordinal>
                        <Active>1</Active>
                        <Periods>...</Periods>
                    </Day>
                    <Day Id="4">
                        <Name>Thursday</Name>
                        <ShortName>Thur</ShortName>
                        <Day>5</Day>
                        <Ordinal>4</Ordinal>
                        <Active>1</Active>
                        <Periods>...</Periods>
                    </Day>
                    <Day Id="5">
                        <Name>Friday</Name>
                        <ShortName>Fri</ShortName>
                        <Day>6</Day>
                        <Ordinal>5</Ordinal>
                        <Active>1</Active>
                        <Periods>...</Periods>
                    </Day>
                </Days>
            </Week>
        </Structure>
    </TimetableManager>
</iSAMS>';


    /**
     * GISAK
     */
    private const GISAK_XML = '<iSAMS>
    <TimetableManager>
        <Structure>
            <Week Id="7">
                <Name>School Week 19-20</Name>
                <ShortName>GIS2019-20</ShortName>
                <Ordinal>1</Ordinal>
                <Active>1</Active>
                <ShowTimetableWeek>1</ShowTimetableWeek>
                <Days>
                    <Day Id="34">
                        <Name>Sunday</Name>
                        <ShortName>Sun</ShortName>
                        <Day>1</Day>
                        <Ordinal>1</Ordinal>
                        <Active>1</Active>
                        <Periods>...</Periods>
                    </Day>
                    <Day Id="47">
                        <Name>Monday</Name>
                        <ShortName>Mon</ShortName>
                        <Day>2</Day>
                        <Ordinal>2</Ordinal>
                        <Active>1</Active>
                        <Periods>...</Periods>
                    </Day>
                    <Day Id="48">
                        <Name>Tuesday</Name>
                        <ShortName>Tue</ShortName>
                        <Day>3</Day>
                        <Ordinal>3</Ordinal>
                        <Active>1</Active>
                        <Periods>...</Periods>
                    </Day>
                    <Day Id="49">
                        <Name>Wednesday</Name>
                        <ShortName>Wed</ShortName>
                        <Day>4</Day>
                        <Ordinal>4</Ordinal>
                        <Active>1</Active>
                        <Periods>...</Periods>
                    </Day>
                    <Day Id="50">
                        <Name>Thursday</Name>
                        <ShortName>Thur</ShortName>
                        <Day>5</Day>
                        <Ordinal>5</Ordinal>
                        <Active>1</Active>
                        <Periods>...</Periods>
                    </Day>
                </Days>
            </Week>
        </Structure>
    </TimetableManager>
</iSAMS>';


    /**
     * @param string $xml
     * @param int    $isamsDay
     * @param int    $schoolboxDay
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidDayCalibrator')]
    public function testValidDayCalibrator(string $xml, int $isamsDay, int $schoolboxDay): void
    {
        $apiKey = 'batchxml:key=asdfg?dayNumberOffset=0';

        /* Build the thing which gets the stuff
         */
        $api = [
            DSN::MODULE_BATCHXML => new Batchxml(new MockFragmentApi($xml, $apiKey))
        ];

        /* Figure out which schoolbox day corresponds to the given isams day
         * This schoolbox day is the actual ui Day e,g, Day 1 to Day 5, not the array index
         */
        $outcome = (new DayCalibrator($api))->determineSchoolboxDay($isamsDay);

        /* There are other assertions that you can try (ctrl-click to follow)
         */
        $this->assertEquals($schoolboxDay, $outcome);
    }

    /**
     * Data provider for testValidDayCalibrator
     *
     * @return array
     */
    public static function providerValidDayCalibrator(): array
    {
        return [

            /* ISASC: Standard Mon-Fri working week
             */
            'ISASC 1' => [
                self::ISASC_XML,
                2, // Monday, isams Day 2
                1, // corresponding schoolbox Day 1
            ],
            'ISASC 2' => [
                self::ISASC_XML,
                3, // Tuesday, isams Day 3
                2, // corresponding schoolbox Day 2
            ],
            'ISASC 3' => [
                self::ISASC_XML,
                4, // Wednesday, isams Day 4
                3, // corresponding schoolbox Day 3
            ],
            'ISASC 4' => [
                self::ISASC_XML,
                5, // Thursday, isams Day 5
                4, // corresponding schoolbox Day 4
            ],
            'ISASC 5' => [
                self::ISASC_XML,
                6, // Friday, isams Day 6
                5, // corresponding schoolbox Day 5
            ],

            /* GISAK: Offset Sun-Thu working week
             */
            'GISAK 1' => [
                self::GISAK_XML,
                1, // Sunday, isams Day 1
                1, // corresponding schoolbox Day 1
            ],
            'GISAK 2' => [
                self::GISAK_XML,
                2, // Monday, isams Day 2
                2, // corresponding schoolbox Day 2
            ],
            'GISAK 3' => [
                self::GISAK_XML,
                3, // Tuesday, isams Day 3
                3, // corresponding schoolbox Day 3
            ],
            'GISAK 4' => [
                self::GISAK_XML,
                4, // Wednesday, isams Day 4
                4, // corresponding schoolbox Day 4
            ],
            'GISAK 5' => [
                self::GISAK_XML,
                5, // Thursday, isams Day 5
                5, // corresponding schoolbox Day 5
            ],
        ];
    }
}
