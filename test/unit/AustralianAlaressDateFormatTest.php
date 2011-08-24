<?php

class AustralianAlaressDateFormatTest extends PHPUnit_Framework_TestCase {

	/**
	 * Australian Date, 12-hour format with time and morning meridiem
	 * Returns correct datetime in mysql format
	 *
	 * @return void
	 */
	public function test12HourWithTimeMorningMeridiem() {
		$input = '23/08/2011 8:15 am';
		$expected = '2011-08-23 08:15:00';

		$date = new Alaress_Date_Format($input);
		$result = $date->getDatabase();

		$this->assertEquals($result, $expected);
	}

	/**
	 * Australian Date, 12-hour format with time and evening meridiem
	 * Returns correct datetime in mysql format
	 *
	 * @return void
	 */
	public function test12HourWithTimeEveningMeridiem() {
		$input = '23/08/2011 8:15 pm';
		$expected = '2011-08-23 20:15:00';

		$date = new Alaress_Date_Format($input);
		$result = $date->getDatabase();

		$this->assertEquals($result, $expected);
	}

	/**
	 * Australian Date, 12-hour format
	 * Returns correct datetime in mysql format
	 *
	 * @return void
	 */
	public function test12Hour() {
		$input = '23/08/2011';
		$expected = '2011-08-23 00:00:00';

		$date = new Alaress_Date_Format($input);
		$result = $date->getDatabase();

		$this->assertEquals($result, $expected);
	}

}
