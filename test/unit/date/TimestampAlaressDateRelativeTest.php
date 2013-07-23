<?php

class TimestampAlaressDateRelativeTest extends PHPUnit_Framework_TestCase {

	public function testRelativeTimeSeconds1() {
		$input = date('U', strtotime('2011-08-31 12:12:00'));
		$expected = '10 seconds ago';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 12:12:10')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}

	public function testRelativeTimeSeconds2() {
		$input = date('U', strtotime('2011-08-31 12:12:01'));
		$expected = '54 seconds ago';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 12:12:55')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}

	public function testRelativeTimeMinutes1() {
		$input = date('U', strtotime('2011-08-31 12:12:01'));
		$expected = '1 minute ago';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 12:13:01')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}

	public function testRelativeTimeMinutes2() {
		$input = date('U', strtotime('2011-08-31 12:12:01'));
		$expected = '2 minutes ago';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 12:14:01')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}

	public function testRelativeTimeHours1() {
		$input = date('U', strtotime('2011-08-31 13:12:01'));
		$expected = '1 hour ago';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 14:12:01')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}

	public function testRelativeTimeHours2() {
		$input = date('U', strtotime('2011-08-31 13:12:01'));
		$expected = '5 hours ago';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 18:12:01')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}

	public function testRelativeTimeDays1() {
		$input = date('U', strtotime('2011-08-30 13:12:01'));
		$expected = 'Yesterday';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 13:12:01')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}

	public function testRelativeTimeDays2() {
		$input = date('U', strtotime('2011-08-26 13:12:01'));
		$expected = '5 days ago';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 13:12:01')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}

	public function testRelativeTimeWeeks1() {
		$input = date('U', strtotime('2011-08-08 05:00:14'));
		$expected = '3 weeks ago';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 17:40:11')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}

	/**
	 * Expected output is in Australian date format
	 *
	 * @return void
	 */
	public function testRelativeTimeAncientHistory() {
		$input = date('U', strtotime('2001-01-03 02:02:12'));
		$expected = 'on 03-01-2001 02:02:12';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 17:40:11')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}
}
