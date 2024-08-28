<?php

/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use Alaress_Date_Format as DateWrapper;
use Datetime as DateObject;
use Schoolbox\Column\Date;

class DateTest extends DatetimeTest
{
    /**
     * @var Date
     */
    protected $object;

    /**
     * @inheritdoc
     */
    public function setupObject()
    {
        $this->object = new Date();
    }

    /**
     * @inheritdoc
     */
    public static function getTestHydrate()
    {
        // Anything that can be parsed as Y-m-d will parse, even if there's
        // trailing characters.
        return [
            [null, false],
            ['2012-01-01 01:40:23', true],
            ['2012-01-01',          true],
            ['0000-01-01 01:40:23', true],
            ['9999-01-01 01:40:23', true],
            ['2012-01',             false],
            ['0000-00-00',          false],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function getTestDehydrate()
    {
        return [
            [null, null],
            [false, null],
            [DateObject::createFromFormat('!Y-m-d', '2012-01-01'), '2012-01-01'],
            [DateObject::createFromFormat('!Y-m-d\\TH:i:s', '2014-01-01T12:34:56'), '2014-01-01'],
            //
            // Timezone should be ignored for this class, whether or not it's supplied.
            [DateObject::createFromFormat('!Y-m-d\\TH:i:sT', '2014-01-01T12:34:56+00:00'), '2014-01-01'],
            [DateObject::createFromFormat('!Y-m-d\\TH:i:sT', '2014-01-01T12:34:56Z'), '2014-01-01'],
            [DateObject::createFromFormat('!Y-m-d\\TH:i:sT', '2014-01-01T12:34:56+10:00'), '2014-01-01'],
            [DateObject::createFromFormat('!Y-m-d\\TH:i:sT', '2014-01-01T12:34:56-10:00'), '2014-01-01'],
            [DateObject::createFromFormat('!Y-m-d\\TH:i:sT', '2014-01-01T12:34:56EST'), '2014-01-01'],
            [DateObject::createFromFormat('!Y-m-d\\TH:i:sT', '2014-01-01T12:34:56Australia/Melbourne'), '2014-01-01'],

            [new DateWrapper('2012-01-01', '!Y-m-d'), '2012-01-01'],
            [new DateWrapper('2014-01-01T12:34:56EST', '!Y-m-d\\TH:i:sT'), '2014-01-01'],

            ['2012-01-01', '2012-01-01'],
            ['2014-01-01T12:34:56EST', '2014-01-01'],
        ];
    }

    /**
     * @return array|array[]
     */
    public static function getTestHydrateWithTimeData()
    {
        return [
            // Date only
            [new Date('00:00:00'), '2012-01-01', '2012-01-01 00:00:00'],
            [Date::startOfDay(),   '2012-01-01', '2012-01-01 00:00:00'],
            [new Date('12:00:00'), '2012-01-01', '2012-01-01 12:00:00'],
            [new Date('23:59:59'), '2012-01-01', '2012-01-01 23:59:59'],
            [Date::endOfDay(),     '2012-01-01', '2012-01-01 23:59:59'],
            // Date and time - time component should be ignored
            [new Date('00:00:00'), '2012-01-01 12:00:00', '2012-01-01 00:00:00'],
            [new Date('23:59:59'), '2012-01-01 12:00:00', '2012-01-01 23:59:59'],
            // Null Date with time - should return empty string
            [new Date('00:00:00'), '',   ''],
            [new Date('00:00:00'), null, ''],
        ];
    }

    /**
     * @return array|array[]
     */
    public static function getTestDehydrateWithTimeData()
    {
        return [
            // Without a time at all
            [new Date('00:00:00'), '2012-01-01', '2012-01-01'],
            // With a time that matches
            [new Date('00:00:00'), '2012-01-01 00:00:00', '2012-01-01'],
            [Date::startOfDay(),   '2012-01-01 00:00:00', '2012-01-01'],
            [new Date('12:00:00'), '2012-01-01 12:00:00', '2012-01-01'],
            [new Date('23:59:59'), '2012-01-01 23:59:59', '2012-01-01'],
            [Date::endOfDay(),     '2012-01-01 23:59:59', '2012-01-01'],
            // With a time that doesn't match
            [new Date('00:00:00'), '2011-12-31 23:59:59', '2011-12-31'],
            [new Date('23:59:59'), '2012-01-01 00:00:00', '2012-01-01'],
        ];
    }

    /**
     *
     * @param Date   $column
     * @param string $input
     * @param string $expectedHydratedValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestHydrateWithTimeData')]
    public function testHydrateWithTime(Date $column, $input, $expectedHydratedValue)
    {
        // Errors are squelched during hydration, as the date and time tests
        // raise a set of warnings due to trailing data.
        $actual = @$column->hydrate($input);
        // Null hydrates to ''
        $this->assertEquals($expectedHydratedValue, $actual ? $actual->getDatabase() : '');
    }

    /**
     * Dehydrate with time should work exactly the same as without time, but
     * let's verify that anyway.
     *
     *
     * @param Date   $column
     * @param string $input
     * @param string $expectedDehydratedValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestDehydrateWithTimeData')]
    public function testDehydrateWithTime(Date $column, $input, $expectedDehydratedValue)
    {
        $this->assertEquals($expectedDehydratedValue, $column->dehydrate($input));
    }
}
