<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Column\Test;
use Schoolbox\Column\Timestamp;
use \DateTime as DateObject;
use \Alaress_Date_Format as DateWrapper;

class TimestampTest extends DatetimeTest {

	/**
	 * @var Timestamp
	 */
	protected $object;

	public function setupObject() {
		$this->object = new Timestamp();
	}

	public function getTestHydrate() {
		//
		// Only Y-m-d format results are able to be hydrated.
		return array(
			array('2012-01-01 01:40:23', false),
			array('2012-01-01',          false),
			array('0000-01-01 01:40:23', false),
			array('9999-01-01 01:40:23', false),
			array(0, true),
			array(1, true),
			array(-1, true),
			array(PHP_INT_MAX, true),
			array(123456789, true),
		);
	}

	public function getTestDehydrate() {
		//
		// I think that most code that uses timestamps is timezone ignorant,
		// so preset timezone.
		return array(
			array(false, null),
			array(DateObject::createFromFormat('!Y-m-dT','1970-01-01Z'), 0),
			array(DateObject::createFromFormat('!Y-m-d H:i:sT' ,'1970-01-01 00:00:01-0100'), 3601),
			array(DateObject::createFromFormat('!Y-m-d H:i:sT' ,'1970-01-01 00:00:00+1000'), -36000),
			array(new DateWrapper('1970-01-01Z', '!Y-m-dT'), 0),
			array(new DateWrapper('1970-01-01 00:00:01-0100', '!Y-m-d H:i:sT' ), 3601),
			array(new DateWrapper('1970-01-01 00:00:00+1000', '!Y-m-d H:i:sT' ), -36000),
		);
	}
}
