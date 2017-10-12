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
use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ResourceBooking\Booking;
use Schoolbox\ResourceBooking\Booking\Validation;
use Schoolbox\ResourceBooking\Booking\Mapper as BookingMapper;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as StatusMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Calendar\Event;


class ValidationTest extends \PHPUnit_Framework_TestCase
{

    private function createValidation()
    {

        return new Validation(new User(), new BookingMapper(), new StatusMapper(), new TermMapper());

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
            ['2017-01-01T00:00:10','2017-01-01T00:00:10', 0],
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
            ['now', 0],
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
            ['PT1H', 'PT0H', 1],
            ['PT0H', 'PT2H', 0],
            ['PT0M', 'PT0M', 0],
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
            ['2017-01-01T00:00:00','2017-01-01T01:00:00', 'PT0H', 0],
            ['2017-01-01T00:00:00','2017-01-01T00:01:00', 'PT0H', 0],
            ['now','now', 'PT1H', 1],
            ['now','now', 'PT0H', 0],
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
            ['2017-01-01T00:00:00','2017-01-01T02:00:00', 'PT2H', 0],
            ['2017-01-01T00:00:00','2017-01-01T02:01:00', 'PT0H', 0],
            ['now','now', 'PT1H', 0],
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
     * Data provider for testSimpleValidateForConflicts
     *
     * @return array
     */
    public function provider_simpleValidateForConflicts() {

        $conflictingBookings1 = (new Collection())
            ->add(
                (new Booking\Model())
                    ->setId(11)
            )
            ->add(
                (new Booking\Model())
                    ->setId(12)
            )
            ->add(
                (new Booking\Model())
                    ->setId(13)
            );

        $conflictingBookings2 = (new Collection())
            ->add(
                (new Booking\Model())
                    ->setId(11)
            );

        $conflictingBookings3 = (new Collection());

        return [
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $conflictingBookings1,
                1
            ],
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $conflictingBookings2,
                1
            ],
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $conflictingBookings3,
                0
            ],
        ];
    }

    /**
     * @covers \Schoolbox\ResourceBooking\Booking\Validation::validateForConflicts
     *
     * @dataProvider provider_simpleValidateForConflicts
     *
     * @param string $startDate
     * @param string $endDate
     * @param Collection  $conflictingBookings
     * @param int    $expected
     */
    public function testSimpleValidateForConflicts($startDate, $endDate, $conflictingBookings, $expected)
    {

        $mockMapper = $this->getMockBuilder(BookingMapper::class)->getMock();
        $mockMapper
            ->method('findForResource')
            ->willReturn($conflictingBookings);

        $validation = new Validation(new User(), $mockMapper, new StatusMapper(), new TermMapper());

        $resource = new Item\Model();
        $resource->setId(123);

        $errors = $validation->validateForConflicts($resource, new DateTime($startDate), new DateTime($endDate));
        $this->assertCount($expected, $errors);
    }

    /**
     * Data provider for testRoomValidateForConflicts
     *
     * @return array
     */
    public function provider_roomValidateForConflicts() {

        $conflictingBookings3 = (new Collection())
            ->add(
                (new Booking\Model())
                    ->setId(11)
            )
            ->add(
                (new Booking\Model())
                    ->setId(12)
            )
            ->add(
                (new Booking\Model())
                    ->setId(13)
            );

        $conflictingBookings1 = (new Collection())
            ->add(
                (new Booking\Model())
                    ->setId(11)
            );

        $conflictingBookings0 = (new Collection());

        $roomEvents3 = (new Collection())
            ->add(
                (new Event())
                ->setId(111)
            )
            ->add(
                 (new Event())
                 ->setId(112)
            )
            ->add(
                (new Event())
                    ->setId(113)
            );

        $roomEvents1 = (new Collection())
            ->add(
                (new Event())
                    ->setId(111)
            );

        $roomEvents0 = (new Collection());

        return [
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $conflictingBookings0,
                'abc1',
                $roomEvents3,
                1
            ],
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $conflictingBookings0,
                'abc1',
                $roomEvents1,
                1
            ],
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $conflictingBookings0,
                'abc1',
                $roomEvents0,
                0
            ],
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $conflictingBookings3,
                'abc1',
                $roomEvents0,
                1
            ],
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $conflictingBookings1,
                'abc1',
                $roomEvents0,
                1
            ],
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $conflictingBookings0,
                '',
                $roomEvents0,
                0
            ],
        ];
    }

    /**
     * @covers \Schoolbox\ResourceBooking\Booking\Validation::validateForConflicts
     *
     * @dataProvider provider_roomValidateForConflicts
     *
     * @param string $startDate
     * @param string $endDate
     * @param array  $conflictiongBookings
     * @param string $roomCode
     * @param Collection $roomEvents
     * @param int    $expected
     */
    public function testRoomValidateForConflicts(
        $startDate,
        $endDate,
        $conflictingBookings,
        $roomCode,
        $roomEvents,
        $expected
    )
    {
        $mockMapper = $this->getMockBuilder(BookingMapper::class)->getMock();
        $mockMapper
            ->method('findForResource')
            ->willReturn($conflictingBookings);

        $mockValidation = $this->getMockBuilder(Validation::Class)
            ->setConstructorArgs([new User(), $mockMapper, new StatusMapper(), new TermMapper()])
            ->setMethods(['getTimetableRoomEvents'])
            ->getMock();
        $mockValidation
            ->method('getTimetableRoomEvents')
            ->willReturn($roomEvents);

        $resource = new Item\Model();
        $resource->setId(123);
        $resource->setRoomCode($roomCode);

        $errors = $mockValidation->validateForConflicts($resource, new DateTime($startDate), new DateTime($endDate));

        $this->assertCount($expected, $errors);
    }

    /**
     * Data provider for testSeriesValidateForConflicts
     *
     * @return array
     */
    public function provider_seriesValidateForConflicts() {

        // Single booking event
        $existingBookingEvent = (new Event())
            ->setId(111);

        // Recursive booking event
        $existingBookingEventInSeries = (new Event())
            ->setId(111)
            ->setSeriesId(22);

        // Conflicting bookings, same Series as $existingBookingEvent2
        $conflictingBookingsSameSeries3 = (new Collection())
            ->add(
                (new Booking\Model())
                    ->setId(11)
                    ->setEvent(
                        (new Event())
                            ->setId(201)
                            ->setSeriesId(22)
                    )
                )
            ->add(
                (new Booking\Model())
                    ->setId(12)
                    ->setEvent(
                        (new Event())
                            ->setId(202)
                            ->setSeriesId(22)
                    )
            )
            ->add(
                (new Booking\Model())
                    ->setId(13)
                    ->setEvent(
                        (new Event())
                            ->setId(203)
                            ->setSeriesId(22)
                    )
            );

        // Conflicting bookings, some other Series
        $conflictingBookingsOtherSeries3 = (new Collection())
            ->add(
                (new Booking\Model())
                    ->setId(11)
                    ->setEvent(
                        (new Event())
                            ->setId(201)
                            ->setSeriesId(33)
                    )
            )
            ->add(
                (new Booking\Model())
                    ->setId(12)
                    ->setEvent(
                        (new Event())
                            ->setId(202)
                            ->setSeriesId(33)
                    )
            )
            ->add(
                (new Booking\Model())
                    ->setId(13)
                    ->setEvent(
                        (new Event())
                            ->setId(203)
                            ->setSeriesId(33)
                    )
            );

        // No conflicting bookings
        $conflictingBookings0 = (new Collection());

        // Conflicting bookings, same Id as existing booking
        $conflictingBookings1 = (new Collection())
            ->add(
                (new Booking\Model())
                    ->setId(1)
                    ->setEvent(
                        (new Event())
                            ->setId(201)
                    )
            );

        // Conflicting bookings, same Id as existing booking
        $conflictingBookingsSameId1 = (new Collection())
            ->add(
                (new Booking\Model())
                    ->setId(2)
                    ->setEvent(
                        (new Event())
                            ->setId(202)
                    )
            );

        return [
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $existingBookingEvent,
                $conflictingBookingsSameSeries3,
                1,
            ],
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $existingBookingEvent,
                $conflictingBookingsOtherSeries3,
                1,
            ],
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $existingBookingEvent,
                $conflictingBookings0,
                0,
            ],
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $existingBookingEventInSeries,
                $conflictingBookingsSameSeries3,
                0,
            ],
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $existingBookingEventInSeries,
                $conflictingBookingsOtherSeries3,
                1
            ],
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $existingBookingEventInSeries,
                $conflictingBookings0,
                0
            ],
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $existingBookingEvent,
                $conflictingBookings1,
                0
            ],
            [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $existingBookingEvent,
                $conflictingBookingsSameId1,
                1
            ],

        ];
    }

    /**
     * @covers \Schoolbox\ResourceBooking\Booking\Validation::validateForConflicts
     *
     * @dataProvider provider_seriesValidateForConflicts
     *
     * @param string $startDate
     * @param string $endDate
     * @param Event  $existingBookingEvent
     * @param Collection $conflictingBookings
     * @param int    $expected
     */
    public function testSeriesValidateForConflicts(
        $startDate,
        $endDate,
        $existingBookingEvent,
        $conflictingBookings,
        $expected
    )
    {

        $mockMapper = $this->getMockBuilder(BookingMapper::class)->getMock();
        $mockMapper
            ->method('findForResource')
            ->willReturn($conflictingBookings);

        $mockExistingBooking = $this->getMockBuilder(Booking\Model::class)
                                    ->setMethods(null)
                                    ->getMock();
        $mockExistingBooking->setId(1);
        $mockExistingBooking->setEvent($existingBookingEvent);

        $mockValidation = $this->getMockBuilder(Validation::Class)
                               ->setConstructorArgs([new User(), $mockMapper, new StatusMapper(), new TermMapper()])
                               ->setMethods(['getTimetableRoomEvents'])
                               ->getMock();

        $resource = new Item\Model();
        $resource->setId(123);

        $errors = $mockValidation->validateForConflicts($resource, new DateTime($startDate), new DateTime($endDate), $mockExistingBooking);

        $this->assertCount($expected, $errors);
    }

}