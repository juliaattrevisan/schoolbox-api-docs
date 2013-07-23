<?php

class InvalidAlaressDateFormatTest extends PHPUnit_Framework_TestCase {

	/**
	 * Empty string input
	 * Returns empty string
	 *
	 * @return void
	 */
	public function testEmptyString() {
		$input = '';
		$date = new Alaress_Date_Format($input, 'y-m-d h:i:s');
		$this->assertFalse($date->isValid());
	}

	/**
	 * Random text string input
	 * Returns empty string
	 *
	 * @return void
	 */
	public function testString() {
		$input = 'gronk de la guber';
		$date = new Alaress_Date_Format($input, 'y-m-d h:i:s');
		$this->assertFalse($date->isValid());
	}

	/**
	 * Random but illegal digits in a date format
	 * Returns empty string
	 *
	 * @return void
	 */
	public function testInvalidDate1() {
		$input = '43/67/2011';
		$date = new Alaress_Date_Format($input, 'd/m/y');
		$this->assertFalse($date->isValid());
	}

	/**
	 * Random but illegal digits in a date format
	 * Returns empty string
	 *
	 * @return void
	 */
	public function testInvalidDate2() {
		$input = '97/27/1011';
		$date = new Alaress_Date_Format($input, 'd/m/y');
		$this->assertFalse($date->isValid());
	}

	/**
	 * Random but illegal digits in a date format
	 * Returns empty string
	 *
	 * @return void
	 */
	public function testInvalidDate3() {
		$input = '4333/6735/34508';
		$date = new Alaress_Date_Format($input, 'd/m/y');
		$this->assertFalse($date->isValid());
	}

	/**
	 * Random but illegal digits in a date format
	 * Returns empty string
	 *
	 * @return void
	 */
	public function testInvalidDate4() {
		$input = '234/82354';
		$date = new Alaress_Date_Format($input, 'd/m/y');
		$this->assertFalse($date->isValid());
	}

	/**
	 * Random but illegal digits in a partial date format
	 * Returns empty string
	 *
	 * @return void
	 */
	public function testPartialDate1() {
		$input = '12/02';
		$date = new Alaress_Date_Format($input, 'd/m/y');
		$this->assertFalse($date->isValid());
	}

	/**
	 * Random but illegal digits in a partial date format
	 * Returns empty string
	 *
	 * @return void
	 */
	public function testPartialDate2() {
		$input = '02-02';
		$date = new Alaress_Date_Format($input, 'd/m/y');
		$this->assertFalse($date->isValid());
	}

	/**
	 * Random but illegal digits in a partial date format
	 * Returns empty string
	 *
	 * @return void
	 */
	public function testPartialDate3() {
		$input = '02-2001';
		$date = new Alaress_Date_Format($input, 'd/m/y');
		$this->assertFalse($date->isValid());
	}
}
