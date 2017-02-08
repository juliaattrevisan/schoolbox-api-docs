<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Column\Test;
use Schoolbox\Column\Date;
use \Datetime as DateObject;
use \Alaress_Date_Format as DateWrapper;

class DateTest extends DatetimeTest {

	/**
	 * @var Date
	 */
	protected $object;

	public function setupObject() {
		$this->object = new Date();
	}

	public function getTestHydrate() {
		// Anything that can be parsed as Y-m-d will parse, even if there's
		// trailing characters.
		return array(
			array(null, false),
			array('2012-01-01 01:40:23', true),
			array('2012-01-01',          true),
			array('0000-01-01 01:40:23', true),
			array('9999-01-01 01:40:23', true),
			array('2012-01', false),
		);
	}

	public function getTestDehydrate() {
		return array(
			array(null, null),
			array(false, null),
			array(DateObject::createFromFormat('!Y-m-d' ,'2012-01-01'), '2012-01-01'),
			array(DateObject::createFromFormat('!Y-m-d\\TH:i:s' ,'2014-01-01T12:34:56'), '2014-01-01'),
			//
			// Timezone should be ignored for this class, whether or not it's supplied.
			array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT' ,'2014-01-01T12:34:56+00:00'), '2014-01-01'),
			array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT' ,'2014-01-01T12:34:56Z'),      '2014-01-01'),
			array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT' ,'2014-01-01T12:34:56+10:00'), '2014-01-01'),
			array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT' ,'2014-01-01T12:34:56-10:00'), '2014-01-01'),
			array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT' ,'2014-01-01T12:34:56EST'), '2014-01-01'),
			array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT' ,'2014-01-01T12:34:56Australia/Melbourne'), '2014-01-01'),

			array(new DateWrapper('2012-01-01', '!Y-m-d'), '2012-01-01'),
			array(new DateWrapper('2014-01-01T12:34:56EST', '!Y-m-d\\TH:i:sT'), '2014-01-01'),

			array('2012-01-01', '2012-01-01'),
			array('2014-01-01T12:34:56EST', '2014-01-01'),
		);
	}

    /**
     * @return array|array[]
     */
    public function getTestHydrateWithTimeData()
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
        ];
    }

    /**
     * @dataProvider getTestHydrateWithTimeData
     *
     * @param Date   $column
     * @param string $input
     * @param string $expectedHydratedValue
     */
    public function testHydrateWithTime(Date $column, $input, $expectedHydratedValue)
    {
        $this->assertEquals($expectedHydratedValue, $column->hydrate($input)->getDatabase());
    }


    /**
     * @return array|array[]
     */
    public function getTestDehydrateWithTimeData()
    {
        return [
            // Without a time at all
            [new Date('00:00:00'), '2012-01-01',          '2012-01-01'],
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
     * Dehydrate with time should work exactly the same as without time, but
     * let's verify that anyway.
     *
     * @dataProvider getTestDehydrateWithTimeData
     *
     * @param Date   $column
     * @param string $input
     * @param string $expectedDehydratedValue
     */
    public function testDehydrateWithTime(Date $column, $input, $expectedDehydratedValue)
    {
        $this->assertEquals($expectedDehydratedValue, $column->dehydrate($input));
    }
}
