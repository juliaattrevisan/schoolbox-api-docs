<?php

class AmericanAlaressDateFormatTest extends PHPUnit_Framework_TestCase {

	/**
	 * 12-hour format with time and morning meridiem
	 * Returns correct datetime in mysql format
	 *
	 * @return void
	 */
	public function test12HourWithTimeMorningMeridiem() {
		$input = '08-23-2011 10:12 AM';
		$expected = '2011-08-23 10:12:00';

		$date = new Alaress_Date_Format($input, 'm-d-y');
		$result = $date->getDatabase();

		$this->assertEquals($result, $expected);
	}

	/**
	 * 12-hour format with time and evening meridiem
	 * Returns correct datetime in mysql format
	 *
	 * @return void
	 */
	public function test12HourWithTimeEveningMeridiem() {
		$input = '08-23-2011 10:12 PM';
		$expected = '2011-08-23 22:12:00';

		$date = new Alaress_Date_Format($input, 'm-d-y');
		$result = $date->getDatabase();

		$this->assertEquals($result, $expected);
	}

	/**
	 * American Date, 12-hour format
	 * Returns correct datetime in mysql format
	 *
	 * @return void
	 */
	public function testAmerican12Hour() {
		$input = '08-23-2011';
		$expected = '2011-08-23 00:00:00';

		$date = new Alaress_Date_Format($input, 'm-d-y');
		$result = $date->getDatabase();

		$this->assertEquals($result, $expected);
	}

	/**
	 * Australian Date, 12-hour format with time and morning meridiem
	 * Returns correct datetime in American format
	 *
	 * @return void
	 */
	public function test12HourWithTimeMorningMeridiemToAmerican() {
		$input = '23/08/2011 8:15 am';
		$expected = '08-23-2011 08:15:00';

		$date = new Alaress_Date_Format($input);
		$result = $date->getAmerican();

		$this->assertEquals($result, $expected);
	}

	/**
	 * Australian Date, 12-hour format with time and morning meridiem
	 * Returns correct datetime in American format
	 *
	 * @return void
	 */
	public function test12HourWithTimeEveningMeridiemToAmerican() {
		$input = '23/08/2011 12:15 Pm';
		$expected = '08-23-2011 12:15:00';

		$date = new Alaress_Date_Format($input);
		$result = $date->getAmerican();

		$this->assertEquals($result, $expected);
	}
}
