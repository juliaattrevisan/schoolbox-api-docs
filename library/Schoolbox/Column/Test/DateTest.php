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

class DateTest extends DatetimeTest {

	/**
	 * @var Datetime
	 */
	protected $object;

	public function setupObject() {
		$this->object = new Date();
	}

	public function getTestHydrate() {
		//
		// Only Y-m-d format results are able to be hydrated.
		return array(
			array('2012-01-01 01:40:23', false),
			array('2012-01-01',          true),
			array('0000-01-01 01:40:23', false),
			array('9999-01-01 01:40:23', false),
		);
	}

	public function getTestDehydrate() {
		return array(
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
		);
	}

//	/**
//	 * @dataProvider getTestHydrate
//	 */
//	public function testHydrate($input, $expected_output) {
//		$actual = $this->object->hydrate($input);
//		if ($actual instanceof DateObject) {
//			$actual = $actual->format('Y-m-d');
//		}
//		$this->assertEquals($expected_output, $actual);
//	}
}
