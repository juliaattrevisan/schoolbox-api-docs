<?php

/**
 * Created by PhpStorm.
 * User: nat
 * Date: 4/10/17
 * Time: 11:05 AM
 */

namespace Schoolbox\ResourceBooking\Test\Booking;

use DateTime;
use DateInterval;
use Alaress\Date\Interval;
use Schoolbox\Collection;
use Schoolbox\Config\Loader;
use Schoolbox\User;
use External_Timetable;
use PHPUnit\Framework\TestCase;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ResourceBooking\Booking;
use Schoolbox\ResourceBooking\Booking\Validation;
use Schoolbox\ResourceBooking\Booking\Mapper as BookingMapper;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as StatusMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Calendar\Event;
use Schoolbox\CampusMapper;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\ResourceBooking\Booking\Validation::class)]
class ValidationTest extends TestCase
{
    /**
     * @param User|null             $user
     * @param BookingMapper|null    $bookingMapper
     * @param StatusMapper|null     $statusMapper
     * @param TermMapper|null       $termMapper
     * @param CampusMapper|null     $campusMapper
     *
     * @return Validation
     */
    private function createValidation(
        $user = null,
        $bookingMapper = null,
        $statusMapper = null,
        $termMapper = null,
        $campusMapper = null,
        $externalEventMapper = null
    ) {

        if (!$user) {
            $user = new User();
        }

        if (!$bookingMapper) {
            $bookingMapper = new BookingMapper();
        }

        if (!$statusMapper) {
            $statusMapper = new StatusMapper();
        }

        if (!$termMapper) {
            $termMapper = new TermMapper();
        }

        if (!$campusMapper) {
            $campusMapper = new CampusMapper();
        }

        if (!$externalEventMapper) {
            $externalEventMapper = new \Schoolbox\External\Event\Mapper();
        }

        $mockConfig = $this->createMock(Loader::class);
        $mockConfig
            ->expects($this->any())
            ->method('get')
            ->willReturnMap([
                ['schooldata_import_data_source', \Schoolbox\External\Importer\Endpoint\Factory::TASS_API,],
            ]);

        // Stub external timetable, don't allow it to use it ist own constractor
        $externalTimetable = $this->getMockBuilder(External_Timetable::Class)
                                  ->onlyMethods(['__construct'])
                                  ->disableOriginalConstructor()
                                  ->getMock();

        /** @var External_Timetable $externalTimetable */
        $mockValidation = $this->getMockBuilder(Validation::Class)
                               ->setConstructorArgs([$user, $bookingMapper, $statusMapper, $termMapper, $externalTimetable, $campusMapper, $mockConfig, $externalEventMapper])
                               ->onlyMethods(['getTimetableRoomEvents']) // we need this to be a stub
                               ->getMock();

        return $mockValidation;
    }

    /**
     * Data provider for testValidateBeforeTime
     *
     * @return array
     */
    public static function providerValidateBeforeTime()
    {
        return [
            'start before end'      => ['2017-01-01T00:00:00','2017-01-01T00:00:10', 0],
            'end before start'      => ['2017-01-01T00:00:10','2017-01-01T00:00:00', 1],
            'same start and end'    => ['2017-01-01T00:00:10','2017-01-01T00:00:10', 0],
        ];
    }

    /**
     *
     *
     * @param string $startDate
     * @param string $endDate
     * @param int $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidateBeforeTime')]
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
    public static function providerValidatePastTime()
    {
        return [
            'Date in future'    => ['2027-01-01T00:00:00', 0],
            'Date in past'      => ['2016-01-01T00:00:00', 1],
            'Date is Now'       => ['now', 0],
        ];
    }

    /**
     *
     *
     * @param string $startDate
     * @param int $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidatePastTime')]
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
    public static function providerValidateLeadTime()
    {
        return [
            '1h lead time, booked 2h before'    => ['PT1H', 'PT2H', 0],
            '1h lead time, booked 30m before'   => ['PT1H', 'PT30M', 1],
            '1h lead time, booked immediately'  => ['PT1H', 'PT0H', 1],
            'no lead time, booked 2h before'    => ['PT0H', 'PT2H', 0],
            'no lead time, booked immediately'  => ['PT0M', 'PT0M', 0],
        ];
    }
    /**
     *
     *
     * @param string $earliestInterval
     * @param string $startInterval
     * @param int $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidateLeadTime')]
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
    public static function providerValidateTooShort()
    {
        return [
            '1h min reservation, booked for 1h'             => ['2017-01-01T00:00:00','2017-01-01T01:00:00', 'PT1H', 0],
            '1h min reservation, booked for 1m'             => ['2017-01-01T00:00:00','2017-01-01T00:01:00', 'PT1H', 1],
            'no min reservation, booked for 1h'             => ['2017-01-01T00:00:00','2017-01-01T01:00:00', 'PT0H', 0],
            'no min reservation, booked for 1m'             => ['2017-01-01T00:00:00','2017-01-01T00:01:00', 'PT0H', 0],
            '1h min reservation, booked form Now to Now'    => ['now','now', 'PT1H', 1],
            'no min reservation, booked form Now to Now'    => ['now','now', 'PT0H', 0],
        ];
    }

    /**
     *
     *
     * @param string $startDate
     * @param string $endDate
     * @param string $minReservation
     * @param int $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidateTooShort')]
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
    public static function providerValidateTooLong()
    {
        return [
            '1h max reservation, booked for 1h'             => ['2017-01-01T00:00:00','2017-01-01T01:00:00', 'PT1H', 0],
            '1h max reservation, booked for 2h'             => ['2017-01-01T00:00:00','2017-01-01T02:00:00', 'PT1H', 1],
            '1h max reservation, booked for 1h 1m'          => ['2017-01-01T00:00:00','2017-01-01T01:01:00', 'PT1H', 1],
            'no max reservation, booked for 1h'             => ['2017-01-01T00:00:00','2017-01-01T01:00:00', 'PT0H', 0],
            'no max reservation, booked from Now to Now'    => ['now','now', 'PT1H', 0],
        ];
    }

    /**
     *
     *
     * @param string $startDate
     * @param string $endDate
     * @param string $maxReservation
     * @param int $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidateTooLong')]
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
    public static function providerValidateCurrentTermRestriction()
    {
        return [
            'booked within the term'    => ['2020-12-31T00:00:00', '2020-12-31', 0],
            'booked outside the term'    => ['2020-12-31T00:00:00', '2020-06-30', 1],
        ];
    }

    /**
     *
     *
     * @param string $endDate
     * @param string $termEndDate
     * @param int $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidateCurrentTermRestriction')]
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
    public static function providerSimpleValidateForConflicts()
    {

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

        $conflictingBookings0 = new Collection();

        return [
            '3 conflicting bookings'    => ['2020-12-31T00:00:00', '2020-12-31T00:00:00', $conflictingBookings3, 1],
            '1 conflicting booking'     => ['2020-12-31T00:00:00', '2020-12-31T00:00:00', $conflictingBookings1, 1],
            'no conflicting booking'    => ['2020-12-31T00:00:00', '2020-12-31T00:00:00', $conflictingBookings0, 0],
        ];
    }

    /**
     *
     *
     * @param string $startDate
     * @param string $endDate
     * @param Collection  $conflictingBookings
     * @param int    $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSimpleValidateForConflicts')]
    public function testSimpleValidateForConflicts($startDate, $endDate, $conflictingBookings, $expected)
    {

        $mockMapper = $this->getMockBuilder(BookingMapper::class)->getMock();
        $mockMapper
            ->method('findForResource')
            ->willReturn($conflictingBookings);

        /** @var BookingMapper $mockMapper */
        $validation = $this->createValidation(new User(), $mockMapper, new StatusMapper(), new TermMapper());

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
    public static function providerRoomValidateForConflicts()
    {

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

        $conflictingBookings0 = new Collection();

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

        $roomEvents0 = new Collection();

        return [
            'No conflicting bookings, 3 timetable events' =>    [
                                                                    '2020-12-31T00:00:00',
                                                                    '2020-12-31T00:00:00',
                                                                    $conflictingBookings0,
                                                                    'abc1',
                                                                    $roomEvents3,
                                                                    1
                                                                ],
            'No conflicting bookings, 1 timetable event' =>     [
                                                                    '2020-12-31T00:00:00',
                                                                    '2020-12-31T00:00:00',
                                                                    $conflictingBookings0,
                                                                    'abc1',
                                                                    $roomEvents1,
                                                                    1
                                                                ],
            'No conflicting bookings, no timetable events' =>   [
                                                                    '2020-12-31T00:00:00',
                                                                    '2020-12-31T00:00:00',
                                                                    $conflictingBookings0,
                                                                    'abc1',
                                                                    $roomEvents0,
                                                                    0
                                                                ],
            '3 conflicting bookings, no timetable events' =>    [
                                                                    '2020-12-31T00:00:00',
                                                                    '2020-12-31T00:00:00',
                                                                    $conflictingBookings3,
                                                                    'abc1',
                                                                    $roomEvents0,
                                                                    1
                                                                ],
            '1 conflicting booking, no timetable events' =>     [
                                                                    '2020-12-31T00:00:00',
                                                                    '2020-12-31T00:00:00',
                                                                    $conflictingBookings1,
                                                                    'abc1',
                                                                    $roomEvents0,
                                                                    1
                                                                ],
            'no conflicting booking, no timetable events' =>    [
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
     *
     *
     * @param string $startDate
     * @param string $endDate
     * @param Collection  $conflictingBookings
     * @param string $roomCode
     * @param Collection $roomEvents
     * @param int    $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerRoomValidateForConflicts')]
    public function testRoomValidateForConflicts(
        $startDate,
        $endDate,
        $conflictingBookings,
        $roomCode,
        $roomEvents,
        $expected
    ) {
        $mockMapper = $this->getMockBuilder(BookingMapper::class)->getMock();
        $mockMapper
            ->method('findForResource')
            ->willReturn($conflictingBookings);

        $mockValidation = $this->createValidation(new User(), $mockMapper, new StatusMapper(), new TermMapper());
        $mockValidation
            ->method('getTimetableRoomEvents')
            ->willReturn($roomEvents);

        $resource = new Item\Model();
        $resource->setId(123);
        $resource->setRoomCode($roomCode);

        /** @var Validation $mockValidation */
        $errors = $mockValidation->validateForConflicts($resource, new DateTime($startDate), new DateTime($endDate));

        $this->assertCount($expected, $errors);
    }

    /**
     * Data provider for testSeriesValidateForConflicts
     *
     * @return array
     */
    public static function providerSeriesValidateForConflicts()
    {

        // Single booking
        $existingBooking = (new Booking\Model())
                    ->setId(1)
                    ->setEvent(
                        (new Event())
                            ->setId(101)
                    );

        // Recursive booking
        $existingBookingEventInSeries = (new Booking\Model())
                    ->setId(1)
                    ->setEvent(
                        (new Event())
                            ->setId(101)
                            ->setSeriesId(10)
                    );

        // Conflicting bookings, same Series as $existingBooking
        $conflictingBookingsSameSeries3 = (new Collection())
            ->add(
                (new Booking\Model())
                    ->setId(2)
                    ->setEvent(
                        (new Event())
                            ->setId(102)
                            ->setSeriesId(10)
                    )
            )
            ->add(
                (new Booking\Model())
                    ->setId(3)
                    ->setEvent(
                        (new Event())
                            ->setId(103)
                            ->setSeriesId(10)
                    )
            )
            ->add(
                (new Booking\Model())
                    ->setId(4)
                    ->setEvent(
                        (new Event())
                            ->setId(104)
                            ->setSeriesId(10)
                    )
            );

        // Conflicting bookings, some other Series
        $conflictingBookingsOtherSeries3 = (new Collection())
            ->add(
                (new Booking\Model())
                    ->setId(11)
                    ->setEvent(
                        (new Event())
                            ->setId(111)
                            ->setSeriesId(20)
                    )
            )
            ->add(
                (new Booking\Model())
                    ->setId(12)
                    ->setEvent(
                        (new Event())
                            ->setId(112)
                            ->setSeriesId(20)
                    )
            )
            ->add(
                (new Booking\Model())
                    ->setId(13)
                    ->setEvent(
                        (new Event())
                            ->setId(113)
                            ->setSeriesId(20)
                    )
            );

        // No conflicting bookings
        $conflictingBookings0 = new Collection();

        // Conflicting bookings, same Id as existing booking
        $conflictingBookings1 = (new Collection())
            ->add(
                (new Booking\Model())
                    ->setId(21)
                    ->setEvent(
                        (new Event())
                            ->setId(221)
                    )
            );

        // Conflicting bookings, different Id
        $conflictingBookingsSameId = (new Collection())
            ->add(
                (new Booking\Model())
                    ->setId(1)
                    ->setEvent(
                        (new Event())
                            ->setId(101)
                    )
            );

        return [
            'booking conflicts with other booking series'           => [
                                                                        '2020-12-31T00:00:00',
                                                                        '2020-12-31T00:00:00',
                                                                        $existingBooking,
                                                                        $conflictingBookingsOtherSeries3,
                                                                        1,
                                                                    ],
            'no conflicting bookings'                               => [
                                                                        '2020-12-31T00:00:00',
                                                                        '2020-12-31T00:00:00',
                                                                        $existingBooking,
                                                                        $conflictingBookings0,
                                                                        0,
                                                                    ],
            'booking in series does not conflict with its series'   => [
                                                                        '2020-12-31T00:00:00',
                                                                        '2020-12-31T00:00:00',
                                                                        $existingBookingEventInSeries,
                                                                        $conflictingBookingsSameSeries3,
                                                                        0,
                                                                    ],
            'booking in series conflicts with other series'         => [
                                                                        '2020-12-31T00:00:00',
                                                                        '2020-12-31T00:00:00',
                                                                        $existingBookingEventInSeries,
                                                                        $conflictingBookingsOtherSeries3,
                                                                        1
                                                                    ],
            'booking in series does not conflict with anything'     => [
                                                                        '2020-12-31T00:00:00',
                                                                        '2020-12-31T00:00:00',
                                                                        $existingBookingEventInSeries,
                                                                        $conflictingBookings0,
                                                                        0
                                                                    ],
            'booking conflicts with another booking'                => [
                                                                        '2020-12-31T00:00:00',
                                                                        '2020-12-31T00:00:00',
                                                                        $existingBooking,
                                                                        $conflictingBookings1,
                                                                        1
                                                                    ],
            'booking does not conflict with itself'                 => [
                                                                        '2020-12-31T00:00:00',
                                                                        '2020-12-31T00:00:00',
                                                                        $existingBooking,
                                                                        $conflictingBookingsSameId,
                                                                        0
                                                                    ],
        ];
    }

    /**
     *
     *
     * @param string $startDate
     * @param string $endDate
     * @param Booking\Model $existingBooking
     * @param Collection $conflictingBookings
     * @param int    $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSeriesValidateForConflicts')]
    public function testSeriesValidateForConflicts(
        $startDate,
        $endDate,
        $existingBooking,
        $conflictingBookings,
        $expected
    ) {
        $mockMapper = $this->getMockBuilder(BookingMapper::class)->getMock();
        $mockMapper
            ->method('findForResource')
            ->willReturn($conflictingBookings);

        $mockValidation = $this->createValidation(new User(), $mockMapper, new StatusMapper(), new TermMapper());

        $resource = new Item\Model();
        $resource->setId(123);

        /** @var Validation $mockValidation */
        $errors = $mockValidation->validateForConflicts($resource, new DateTime($startDate), new DateTime($endDate), $existingBooking);

        $this->assertCount($expected, $errors);
    }
}
