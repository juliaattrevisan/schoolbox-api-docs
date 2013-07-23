<?php

class DatetimeAlaressDateRelativeTest extends PHPUnit_Framework_TestCase {

	public function testRelativeTimePastSeconds1() {
		$input = '2011-08-31 12:12:00';
		$expected = '10 seconds ago';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 12:12:10')));

		$result = $relativeDate->getRelativeTime();

        $this->assertEquals($expected, $result);
    }

	public function testRelativeTimePastSeconds2() {
		$input = '2011-08-31 12:12:01';
		$expected = '54 seconds ago';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 12:12:55')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}

	public function testRelativeTimePastMinutes1() {
		$input = '2011-08-31 12:12:01';
		$expected = '1 minute ago';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 12:13:01')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}

	public function testRelativeTimePastMinutes2() {
		$input = '2011-08-31 12:12:01';
		$expected = '2 minutes ago';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 12:14:01')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}

	public function testRelativeTimePastHours1() {
		$input = '2011-08-31 13:12:01';
		$expected = '1 hour ago';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 14:12:01')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}

	public function testRelativeTimePastHours2() {
		$input = '2011-08-31 13:12:01';
		$expected = '5 hours ago';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 18:12:01')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}

	public function testRelativeTimePastDays1() {
		$input = '2011-08-30 13:12:01';
		$expected = 'Yesterday';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 13:12:01')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}

	public function testRelativeTimePastDays2() {
		$input = '2011-08-26 13:12:01';
		$expected = '5 days ago';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 13:12:01')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}

    public function testRelativeTimePastWeeks1() {
        $input = '2011-08-23 05:00:14';
        $expected = 'Last week';

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-08-31 17:40:11')));

        $result = $relativeDate->getRelativeTime();

        $this->assertEquals($expected, $result);
    }


    public function testRelativeTimePastWeeks2() {
        $input = '2011-08-08 05:00:14';
        $expected = '3 weeks ago';

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-08-31 17:40:11')));

        $result = $relativeDate->getRelativeTime();

        $this->assertEquals($expected, $result);
    }



    public function testRelativeTimePastMonths1() {
        $input = '2011-06-08 05:00:14';
        $expected = '2 months ago';

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-08-08 17:40:11')));

        $result = $relativeDate->getRelativeTime();

        $this->assertEquals($expected, $result);
    }

    /**
	 * Expected output is in Australian date format
	 *
	 * @return void
	 */
	public function testRelativeTimePastAncientHistory() {
		$input = '2001-01-03 02:02:12';
		$expected = 'on 3rd Jan 2001';

		$relativeDate = new Alaress_Date_Relative($input);
		$relativeDate->setNow(date('U', strtotime('2011-08-31 17:40:11')));

		$result = $relativeDate->getRelativeTime();

		$this->assertEquals($expected, $result);
	}


    public function testRelativeTimePresent() {
        $input = '2011-06-08 05:01:01';
        $expected = 'just then';

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime($input)));

        $result = $relativeDate->getRelativeTime();

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureSeconds1() {
        $input = '2011-06-08 05:01:03';
        $expected = 'in 2 seconds';

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-06-08 05:01:01')));

        $result = $relativeDate->getRelativeTime();
        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureSeconds2() {
        $input = '2011-06-08 05:02:00';
        $expected = 'in 59 seconds';

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-06-08 05:01:01')));

        $result = $relativeDate->getRelativeTime();
        $this->assertEquals($expected, $result);
    }


    public function testRelativeTimeFutureMinutes1() {
        $input = '2011-06-08 05:02:05';
        $expected = 'in 1 minute';

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-06-08 05:01:01')));

        $result = $relativeDate->getRelativeTime();
        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureMinutes2() {
        $input = '2011-06-08 05:55:00';
        $expected = 'in 54 minutes';

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-06-08 05:01:01')));

        $result = $relativeDate->getRelativeTime();
        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureHours1() {
        $input = '2011-06-08 06:10:00';
        $expected = 'in 1 hour';

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-06-08 05:01:01')));

        $result = $relativeDate->getRelativeTime();
        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureHours2() {
        $input = '2011-06-09 04:30:00';
        $expected = 'in 23 hours';

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-06-08 05:01:01')));

        $result = $relativeDate->getRelativeTime();
        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureDays1() {
        $input = '2011-06-09 06:01:03';
        $expected = 'Tomorrow';

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-06-08 05:01:01')));

        $result = $relativeDate->getRelativeTime();
        $this->assertEquals($expected, $result);
    }

    /**
     * A future date within 3 days will display as 'on {dayname}'
     */
    public function testRelativeTimeFutureDays2() {
        $input = '2011-06-10 10:01:01';
        $dayofweek = date('l', strtotime($input));
        $expected = 'on ' . $dayofweek;

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-06-08 05:01:01')));

        $result = $relativeDate->getRelativeTime();
        $this->assertEquals($expected, $result);
    }


    /**
     * A future date between 7 and 13 days will display as 'next {dayname}'
     */
    public function testRelativeTimeFutureDays3() {
        $input = '2011-06-17 10:01:01';
        $dayofweek = date('l', strtotime($input));
        $expected = 'next ' . $dayofweek;

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-06-08 05:01:01')));

        $result = $relativeDate->getRelativeTime();
        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureWeeks1() {
        $input = '2011-06-22 05:01:01';
        $expected = 'in 2 weeks';

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-06-08 05:01:01')));

        $result = $relativeDate->getRelativeTime();
        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureWeeks2() {
        $input = '2011-07-09 05:01:02';
        $expected = 'in 4 weeks';

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-06-08 05:01:01')));

        $result = $relativeDate->getRelativeTime();
        $this->assertEquals($expected, $result);
    }

    /**
     * A future date more than 4 weeks will display in months
     */
    public function testRelativeTimeFutureMonths1() {
        $input = '2011-03-05 05:01:01';
        $expected = 'in 1 month';

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-02-01 05:01:01')));

        $result = $relativeDate->getRelativeTime();
        $this->assertEquals($expected, $result);
    }

    /**
     * A future date between 1 and 11 months will display in months
     */
    public function testRelativeTimeFutureMonths2() {
        $input = '2012-01-10 05:01:01';
        $expected = 'in 11 months';

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-02-01 05:01:01')));

        $result = $relativeDate->getRelativeTime();
        $this->assertEquals($expected, $result);
    }

    /**
     * A future date between 1 and 11 months will display in months
     */
    public function testRelativeTimeDystopia() {
        $input = '2211-06-10 10:01:01';
        $expected = 'on 10th Jun 2211';

        $relativeDate = new Alaress_Date_Relative($input);
        $relativeDate->setNow(date('U', strtotime('2011-06-08 05:01:01')));

        $result = $relativeDate->getRelativeTime();
        $this->assertEquals($expected, $result);
    }






}
