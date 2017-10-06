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
use Schoolbox\User;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ResourceBooking\Booking;
use Schoolbox\ResourceBooking\Booking\Validation;
use Schoolbox\ResourceBooking\Booking\Mapper as BookingMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term\Model as Term;


class ValidationTest extends \PHPUnit_Framework_TestCase
{

    private function createValidation()
    {

        return new Validation(new User(), new BookingMapper(), new TermMapper());

    }

    /**
     * Data provider for testValidateBeforeTime
     *
     * @return array
     */
    public function provider_testStartEnd() {
        return [
            ['2017-01-01T00:00:00','2017-01-01T00:00:10', 0],
            ['2017-01-01T00:00:10','2017-01-01T00:00:00', 1],
        ];
    }

    /**
     * @covers Schoolbox\ResourceBooking\Booking\Validation::validateBeforeTime
     *
     * @dataProvider provider_testStartEnd
     *
     * @param string $startDate
     * @param string $endDate
     * @param int $expected
     */
    public function testValidateBeforeTime($startDate, $endDate, $expected)
    {
        $validation = $this->createValidation();
        $errors = $validation->validateBeforeTime(new DateTime($startDate), new DateTime($endDate));
        $this->assertCount($expected, $errors);
    }

//    public function testValidateTooShort()
//    {
//        $resource = new Item\Model();
//        $interval = Interval::createFromDateInterval(new DateInterval('PT1H'));
//        $resource->setMinReservationTime($interval);
//
//        $validation = new Validation();
//        $startDate = new DateTime('2017-01-01T00:00:00');
//        $endDate = new DateTime('2017-01-01T01:00:00');
//        $errors = $validation->validateTooShort($resource, $startDate, $endDate);
//        $this->assertCount(0, $errors);
//    }
//
//    public function testValidateTooShortError()
//    {
//        $resource = new Item\Model();
//        $interval = Interval::createFromDateInterval(new DateInterval('PT1H'));
//        $resource->setMinReservationTime($interval);
//
//        $validation = new Validation();
//        $startDate = new DateTime('2017-01-01T00:00:00');
//        $endDate = new DateTime('2017-01-01T00:01:00');
//        $errors = $validation->validateTooShort($resource, $startDate, $endDate);
//        $this->assertCount(1, $errors);
//    }
//
//    public function testValidatePastTime()
//    {
//
//        $validation = new Validation();
//        $startDate = new DateTime('2027-01-01T00:00:00');
//        $errors = $validation->validatePastTime($startDate);
//        $this->assertCount(0, $errors);
//    }
//
//    public function testValidatePastTimeError()
//    {
//
//        $validation = new Validation();
//        $startDate = new DateTime('2016-01-01T00:00:00');
//        $errors = $validation->validatePastTime($startDate);
//        $this->assertCount(1, $errors);
//    }
//
//    public function testValidateLeadTime()
//    {
//        $resource = new Item\Model();
//        $earliest = Interval::createFromDateInterval(new DateInterval('PT1H'));
//        $resource->setMinLeadTime($earliest);
//
//        $validation = new Validation();
//        $now = new DateTime();
//        $startDate = $now->add(new DateInterval('PT2H'));
//        $errors = $validation->validateLeadTime($resource, $startDate);
//        $this->assertCount(0, $errors);
//    }

//    public function testValidateLeadTimeError()
//    {
//        $resource = new Item\Model();
//        $earliest = Interval::createFromDateInterval(new DateInterval('PT1H'));
//        $resource->setMinLeadTime($earliest);
//
//        $validation = new Validation(new \Schoolbox\User(), new \Schoolbox\ResourceBooking\Booking\Mapper(), new \Schoolbox\Assessment\Term\Mapper());
//        $now = new DateTime();
//        $startDate = $now->add(new DateInterval('PT30M'));
//        $errors = $validation->validateLeadTime($resource, $startDate);
//        $this->assertCount(1, $errors);
//    }

    public function testValidateCurrentTermRestriction()
    {
        $validation = $this->createValidation();
        $endDate = new DateTime('2020-12-31T00:00:00');

        $term = new Term();
        $term->setDisplayFinish(new \Alaress_Date_Format('2020-12-31', \Alaress_Date_Format::DATABASE_DATE_FORMAT));

        $errors = $validation->validateCurrentTermRestriction($endDate, $term);
        $this->assertCount(0, $errors);

    }

    public function testValidateCurrentTermRestrictionError()
    {
        $validation = $this->createValidation();
        $endDate = new DateTime('2020-12-31T00:00:00');

        $term = new Term();
        $term->setDisplayFinish(new \Alaress_Date_Format('2020-06-30', \Alaress_Date_Format::DATABASE_DATE_FORMAT));

        $errors = $validation->validateCurrentTermRestriction($endDate, $term);
        $this->assertCount(1, $errors);

    }


}