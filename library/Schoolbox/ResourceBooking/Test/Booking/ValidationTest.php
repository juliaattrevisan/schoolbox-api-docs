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
use Jenssegers\Date\Date;
use Schoolbox\User;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ResourceBooking\Booking\Validation;
use Schoolbox\ResourceBooking\Booking\Mapper as BookingMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term\Model as Term;


class ValidationTest extends \PHPUnit_Framework_TestCase
{

    private function createValidation()
    {

        return new Validation(new User(), new BookingMapper(), new TermMapper());
//        return $this->getMock( '\Schoolbox\ResourceBooking\Booking\Validation', [], [new User(), new BookingMapper(), new TermMapper()]);
//        return $this->getMockBuilder(Validation::class)
//                             ->setConstructorArgs([new User(), new BookingMapper(), new TermMapper()])
//                             ->setMethods(null)
//                             ->getMock();
    }

    /**
     * Data provider for testValidateBeforeTime
     *
     * @return array
     */
    public function provider_validateBeforeTime() {
        return [
            ['2017-01-01T00:00:00','2017-01-01T00:00:10', 0],
            ['2017-01-01T00:00:10','2017-01-01T00:00:00', 1],
        ];
    }

    /**
     * @covers \Schoolbox\ResourceBooking\Booking\Validation::validateBeforeTime
     *
     * @dataProvider provider_validateBeforeTime
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

    /**
     * Data provider for testValidatePastTime
     *
     * @return array
     */
    public function provider_validatePastTime() {
        return [
            ['2027-01-01T00:00:00', 0],
            ['2016-01-01T00:00:00', 1],
        ];
    }

    /**
     * @covers \Schoolbox\ResourceBooking\Booking\Validation::validatePastTime
     *
     * @dataProvider provider_validatePastTime
     *
     * @param string $startDate
     * @param int $expected
     */
    public function testValidatePastTime($startDate, $expected)
    {

        $validation = $this->createValidation();
        $errors = $validation->validatePastTime(new DateTime($startDate));
        $this->assertCount($expected, $errors);
    }

    /**
     * Data provider for testValidateLeadTime
     *
     * @return array
     */
    public function provider_validateLeadTime() {
        return [
            ['PT1H', 'PT2H', 0],
            ['PT1H', 'PT30M', 1],
        ];
    }
    /**
     * @covers \Schoolbox\ResourceBooking\Booking\Validation::validateLeadTime
     *
     * @dataProvider provider_validateLeadTime
     *
     * @param string $earliestInterval
     * @param string $startInterval
     * @param int $expected
     */
    public function testValidateLeadTime($earliestInterval, $startInterval, $expected)
    {
        $resource = new Item\Model();
        $earliest = Interval::createFromDateInterval(new DateInterval($earliestInterval));
        $resource->setMinLeadTime($earliest);

        $validation = $this->createValidation();
        $now = new DateTime();
        $startDate = $now->add(new DateInterval($startInterval));
        $errors = $validation->validateLeadTime($resource, $startDate);
        $this->assertCount($expected, $errors);
    }

    /**
     * Data provider for testValidateTooShort
     *
     * @return array
     */
    public function provider_validateTooShort() {
        return [
            ['2017-01-01T00:00:00','2017-01-01T01:00:00', 'PT1H', 0],
            ['2017-01-01T00:00:00','2017-01-01T00:01:00', 'PT1H', 1],
        ];
    }

    /**
     * @covers \Schoolbox\ResourceBooking\Booking\Validation::validateTooShort
     *
     * @dataProvider provider_validateTooShort
     *
     * @param string $startDate
     * @param string $endDate
     * @param string $minReservation
     * @param int $expected
     */
    public function testValidateTooShort($startDate, $endDate, $minReservation, $expected)
    {
        $resource = new Item\Model();
        $resource->setMinReservationTime(Interval::createFromDateInterval(new DateInterval($minReservation)));

        $validation = $this->createValidation();
        $errors = $validation->validateTooShort($resource, new DateTime($startDate), new DateTime($endDate));
        $this->assertCount($expected, $errors);
    }

    /**
     * Data provider for testValidateTooLong
     *
     * @return array
     */
    public function provider_validateTooLong() {
        return [
            ['2017-01-01T00:00:00','2017-01-01T01:00:00', 'PT1H', 0],
            ['2017-01-01T00:00:00','2017-01-01T02:01:00', 'PT1H', 1],
        ];
    }

    /**
     * @covers \Schoolbox\ResourceBooking\Booking\Validation::validateTooLong
     *
     * @dataProvider provider_validateTooLong
     *
     * @param string $startDate
     * @param string $endDate
     * @param string $maxReservation
     * @param int $expected
     */
    public function testValidateTooLong($startDate, $endDate, $maxReservation, $expected)
    {
        $resource = new Item\Model();
        $resource->setMaxReservationTime(Interval::createFromDateInterval(new DateInterval($maxReservation)));

        $validation = $this->createValidation();
        $errors = $validation->validateTooLong($resource, new DateTime($startDate), new DateTime($endDate));
        $this->assertCount($expected, $errors);
    }

    /**
     * Data provider for testValidateCurrentTermRestriction
     *
     * @return array
     */
    public function provider_validateCurrentTermRestriction() {
        return [
            ['2020-12-31T00:00:00', '2020-12-31', 0],
            ['2020-12-31T00:00:00', '2020-06-30', 1],
        ];
    }

    /**
     * @covers \Schoolbox\ResourceBooking\Booking\Validation::validateCurrentTermRestriction
     *
     * @dataProvider provider_validateCurrentTermRestriction
     *
     * @param string $endDate
     * @param string $termEndDate
     * @param int $expected
     */
    public function testValidateCurrentTermRestriction($endDate, $termEndDate, $expected)
    {
        $validation = $this->createValidation();

        $term = new Term();
        $term->setDisplayFinish(new \Alaress_Date_Format($termEndDate, \Alaress_Date_Format::DATABASE_DATE_FORMAT));

        $errors = $validation->validateCurrentTermRestriction(new DateTime($endDate), $term);
        $this->assertCount($expected, $errors);

    }

    /**
     * Data provider for testValidateForConflicts
     *
     * @return array
     */
    public function provider_validateForConflicts() {
        return [
            ['2020-12-31T00:00:00',
             '2020-12-31T00:00:00',
             [1,2,3],
             1],
            ['2020-12-31T00:00:00',
             '2020-12-31T00:00:00',
             [1],
             1],
            ['2020-12-31T00:00:00',
             '2020-12-31T00:00:00',
             [],
             0],
        ];
    }

    /**
     * @covers \Schoolbox\ResourceBooking\Booking\Validation::validateForConflicts
     *
     * @dataProvider provider_validateForConflicts
     *
     * @param string $startDate
     * @param string $endDate
     * @param array $collection
     * @param int $expected
     */
    public function testValidateForConflicts($startDate, $endDate, $collection, $expected)
    {

        $mockMapper = $this->getMockBuilder(BookingMapper::class)->getMock();
        $mockMapper
            ->method('findForResource')
            ->willReturn($collection);

        $validation = new Validation(new User(), $mockMapper, new TermMapper());

        $resource = new Item\Model();
        $resource->setId(123);

        $errors = $validation->validateForConflicts($resource, new DateTime($startDate), new DateTime($endDate));
        $this->assertCount($expected, $errors);
    }

}