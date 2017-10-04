<?php
/**
 * Created by PhpStorm.
 * User: nat
 * Date: 4/10/17
 * Time: 11:05 AM
 */

namespace Schoolbox\ResourceBooking\Test;

use DateTime;
use DateInterval;
use Alaress\Date\Interval;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ResourceBooking\Booking\Validation;
use Schoolbox\Assessment\Term\Mapper as TermMapper;


class ValidationTest extends \PHPUnit_Framework_TestCase
{

    public function testValidateBeforeTime()
    {
        $validation = new Validation();
        $startDate = new DateTime('2017-01-01T00:00:00');
        $endDate = new DateTime('2017-01-01T00:00:10');
        $errors = $validation->validateBeforeTime($startDate, $endDate);
        $this->assertCount(0, $errors);
    }

    public function testValidateBeforeTimeError()
    {
        $validation = new Validation();
        $startDate = new DateTime('2017-01-01T00:00:10');
        $endDate = new DateTime('2017-01-01T00:00:00');
        $errors = $validation->validateBeforeTime($startDate, $endDate);
        $this->assertCount(1, $errors);
    }

    public function testValidateTooShort()
    {
        $resource = new Item\Model();
        $interval = Interval::createFromDateInterval(new DateInterval('PT1H'));
        $resource->setMinReservationTime($interval);

        $validation = new Validation();
        $startDate = new DateTime('2017-01-01T00:00:00');
        $endDate = new DateTime('2017-01-01T01:00:00');
        $errors = $validation->validateTooShort($resource, $startDate, $endDate);
        $this->assertCount(0, $errors);
    }

    public function testValidateTooShortError()
    {
        $resource = new Item\Model();
        $interval = Interval::createFromDateInterval(new DateInterval('PT1H'));
        $resource->setMinReservationTime($interval);

        $validation = new Validation();
        $startDate = new DateTime('2017-01-01T00:00:00');
        $endDate = new DateTime('2017-01-01T00:01:00');
        $errors = $validation->validateTooShort($resource, $startDate, $endDate);
        $this->assertCount(1, $errors);
    }

    public function testValidatePastTime()
    {

        $validation = new Validation();
        $startDate = new DateTime('2027-01-01T00:00:00');
        $errors = $validation->validatePastTime($startDate);
        $this->assertCount(0, $errors);
    }

    public function testValidatePastTimeError()
    {

        $validation = new Validation();
        $startDate = new DateTime('2016-01-01T00:00:00');
        $errors = $validation->validatePastTime($startDate);
        $this->assertCount(1, $errors);
    }

    public function testValidateLeadTime()
    {
        $resource = new Item\Model();
        $earliest = Interval::createFromDateInterval(new DateInterval('PT1H'));
        $resource->setMinLeadTime($earliest);

        $validation = new Validation();
        $now = new DateTime();
        $startDate = $now->add(new DateInterval('PT2H'));
        $errors = $validation->validateLeadTime($resource, $startDate);
        $this->assertCount(0, $errors);
    }

    public function testValidateLeadTimeError()
    {
        $resource = new Item\Model();
        $earliest = Interval::createFromDateInterval(new DateInterval('PT1H'));
        $resource->setMinLeadTime($earliest);

        $validation = new Validation();
        $now = new DateTime();
        $startDate = $now->add(new DateInterval('PT30M'));
        $errors = $validation->validateLeadTime($resource, $startDate);
        $this->assertCount(1, $errors);
    }
}