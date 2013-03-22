<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Column\Test;
use Schoolbox\Column\Datetime;
use \Datetime as DateObject;
use \Alaress_Date_Format as DateWrapper;

class DatetimeTest extends TestCase {

	/**
	 * @var Datetime
	 */
	protected $object;

	public function setupObject() {
		$this->object = new Datetime();
	}

	/**
	 * Data provider for $this->testHydrate().
	 *
	 * Only interested in whether a value hydrates into a DateTime object - not
	 * interested in its value (which is obvious from the value passed to
	 * hydrate method)
	 *
	 * @return array
	 */
	public function getTestHydrate() {
		return array(
			array('2012-01-01 01:40:23', true),
			array('2012-01-01',          false),
			array('0000-01-01 01:40:23', true),
			array('9999-01-01 01:40:23', true),
		);
	}

	public function getTestDehydrate() {
		return array(
			array(false, null),
			array(DateObject::createFromFormat('!Y-m-d' ,'2012-01-01'), '2012-01-01 00:00:00'),
			array(DateObject::createFromFormat('!Y-m-d\\TH:i:s' ,'2014-01-01T12:34:56'), '2014-01-01 12:34:56'),
			//
			// Timezone should be ignored for this class, whether or not it's supplied.
			array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT' ,'2014-01-01T12:34:56+00:00'), '2014-01-01 12:34:56'),
			array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT' ,'2014-01-01T12:34:56Z'),      '2014-01-01 12:34:56'),
			array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT' ,'2014-01-01T12:34:56+10:00'), '2014-01-01 12:34:56'),
			array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT' ,'2014-01-01T12:34:56-10:00'), '2014-01-01 12:34:56'),
			array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT' ,'2014-01-01T12:34:56EST'), '2014-01-01 12:34:56'),
			array(DateObject::createFromFormat('!Y-m-d\\TH:i:sT' ,'2014-01-01T12:34:56Australia/Melbourne'), '2014-01-01 12:34:56'),

			array(new DateWrapper('2012-01-01', '!Y-m-d'), '2012-01-01 00:00:00'),
			array(new DateWrapper('2014-01-01T12:34:56EST', '!Y-m-d\\TH:i:sT'), '2014-01-01 12:34:56'),
		);
	}

	/**
	 * @dataProvider getTestHydrate
	 */
	public function testHydrate($input, $expected_output) {
		$this->assertEquals($expected_output, ($this->object->hydrate($input) instanceof DateWrapper));
	}

	/**
	 * @dataProvider getTestDehydrate
	 */
	public function testDehydrate($input, $expected_output) {
		//
		// Test these with Alaress_Date_Format objects too.
		$this->assertEquals($expected_output, $this->object->dehydrate($input));
	}
}
