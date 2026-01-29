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
        $conflictingBookings3 = new Collection()
            ->add(new Booking\Model()->setId(11))
            ->add(new Booking\Model()->setId(12))
            ->add(new Booking\Model()->setId(13));

        $conflictingBookings1 = new Collection()
            ->add(new Booking\Model()->setId(11));

        $conflictingBookings0 = new Collection();

        return [
            '3 conflicting bookings'    => ['2020-12-31T00:00:00', '2020-12-31T00:00:00', $conflictingBookings3, 1],
            '1 conflicting booking'     => ['2020-12-31T00:00:00', '2020-12-31T00:00:00', $conflictingBookings1, 1],
            'no conflicting booking'    => ['2020-12-31T00:00:00', '2020-12-31T00:00:00', $conflictingBookings0, 0],
        ];
    }

    /**
     * Note this doesn't actually test the clash detection logic.
     * It just checks that the number of errors returned is as expected,
     * given that the provided set of clashing bookings was found.
     *
     * @param string     $startDate
     * @param string     $endDate
     * @param Collection $conflictingBookings
     * @param int        $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSimpleValidateForConflicts')]
    public function testSimpleValidateForConflicts(string $startDate, string $endDate, Collection $conflictingBookings, int $expected): void
    {
        $mockMapper = $this->getMockBuilder(BookingMapper::class)->getMock();
        $mockMapper
            ->method('findForResource')
            ->willReturn($conflictingBookings);

        /** @var BookingMapper $mockMapper */
        $validation = $this->createValidation(new User(), $mockMapper, new StatusMapper(), new TermMapper());

        $resource = new Item\Model();
        $resource->setId(123);

        $errors = $validation->validateForClashes($resource, new DateTime($startDate), new DateTime($endDate));
        $errorsToCount = $validation->getAllErrorStringsAsFlatArray($errors);

        $this->assertCount($expected, $errorsToCount);
    }

    /**
     * Data provider for testRoomValidateForConflicts
     *
     * @return array
     */
    public static function providerRoomValidateForConflicts()
    {
        $conflictingBookings3 = new Collection()
            ->add(new Booking\Model()->setId(11))
            ->add(new Booking\Model()->setId(12))
            ->add(new Booking\Model()->setId(13));

        $conflictingBookings1 = new Collection()
            ->add(new Booking\Model()->setId(11));

        $conflictingBookings0 = new Collection();

        $roomEvents3 = new Collection()
            ->add(new Event()->setId(111))
            ->add(new Event()->setId(112))
            ->add(new Event()->setId(113));

        $roomEvents1 = new Collection()
            ->add(new Event()->setId(111));

        $roomEvents0 = new Collection();

        return [
            'No conflicting bookings, 3 timetable events' => [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $conflictingBookings0,
                'abc1',
                $roomEvents3,
                1
            ],
            'No conflicting bookings, 1 timetable event' => [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $conflictingBookings0,
                'abc1',
                $roomEvents1,
                1
            ],
            'No conflicting bookings, no timetable events' => [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $conflictingBookings0,
                'abc1',
                $roomEvents0,
                0
            ],
            '3 conflicting bookings, no timetable events' => [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $conflictingBookings3,
                'abc1',
                $roomEvents0,
                1
            ],
            '1 conflicting booking, no timetable events' => [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $conflictingBookings1,
                'abc1',
                $roomEvents0,
                1
            ],
            'no conflicting booking, no timetable events' => [
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
     * Note this doesn't actually test the clash detection logic.
     * It just checks that the number of errors returned is as expected,
     * given that the provided set of clashing bookings and room events was found.
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
        string $startDate,
        string $endDate,
        Collection $conflictingBookings,
        string $roomCode,
        Collection $roomEvents,
        int $expected
    ): void {
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
        $errors = $mockValidation->validateForClashes($resource, new DateTime($startDate), new DateTime($endDate));
        $errorsToCount = $mockValidation->getAllErrorStringsAsFlatArray($errors);

        $this->assertCount($expected, $errorsToCount);
    }

    /**
     * Data provider for testSeriesValidateForConflicts
     *
     * @return array
     */
    public static function providerSeriesValidateForConflicts()
    {
        // Single booking
        $existingBooking = new Booking\Model()
                    ->setId(1)
                    ->setEvent(new Event()->setId(101));

        // Recursive booking
        $existingBookingEventInSeries = new Booking\Model()
                    ->setId(1)
                    ->setEvent(new Event()->setId(101)->setSeriesId(10));

        // Conflicting bookings, same Series as $existingBooking
        $conflictingBookingsSameSeries3 = new Collection()
            ->add(
                new Booking\Model()
                    ->setId(2)
                    ->setEvent(new Event()->setId(102)->setSeriesId(10))
            )
            ->add(
                new Booking\Model()
                    ->setId(3)
                    ->setEvent(new Event()->setId(103)->setSeriesId(10))
            )
            ->add(
                new Booking\Model()
                    ->setId(4)
                    ->setEvent(new Event()->setId(104)->setSeriesId(10))
            );

        // Conflicting bookings, some other Series
        $conflictingBookingsOtherSeries3 = new Collection()
            ->add(
                new Booking\Model()
                    ->setId(11)
                    ->setEvent(new Event()->setId(111)->setSeriesId(20))
            )
            ->add(
                new Booking\Model()
                    ->setId(12)
                    ->setEvent(new Event()->setId(112)->setSeriesId(20))
            )
            ->add(
                new Booking\Model()
                    ->setId(13)
                    ->setEvent(new Event()->setId(113)->setSeriesId(20))
            );

        // No conflicting bookings
        $conflictingBookings0 = new Collection();

        // Conflicting bookings, same Id as existing booking
        $conflictingBookings1 = new Collection()
            ->add(
                new Booking\Model()
                    ->setId(21)
                    ->setEvent(new Event()->setId(221))
            );

        // Conflicting bookings, different Id
        $conflictingBookingsSameId = new Collection()
            ->add(
                new Booking\Model()
                    ->setId(1)
                    ->setEvent(new Event()->setId(101))
            );

        return [
            'booking conflicts with other booking series' => [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $existingBooking,
                $conflictingBookingsOtherSeries3,
                1,
            ],
            'no conflicting bookings' => [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $existingBooking,
                $conflictingBookings0,
                0,
            ],
            'booking in series does not conflict with its series' => [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $existingBookingEventInSeries,
                $conflictingBookingsSameSeries3,
                0,
            ],
            'booking in series conflicts with other series' => [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $existingBookingEventInSeries,
                $conflictingBookingsOtherSeries3,
                1
            ],
            'booking in series does not conflict with anything' => [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $existingBookingEventInSeries,
                $conflictingBookings0,
                0
            ],
            'booking conflicts with another booking' => [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $existingBooking,
                $conflictingBookings1,
                1
            ],
            'booking does not conflict with itself' => [
                '2020-12-31T00:00:00',
                '2020-12-31T00:00:00',
                $existingBooking,
                $conflictingBookingsSameId,
                0
            ],
        ];
    }

    /**
     * Note this doesn't actually test the clash detection logic.
     * It just checks that the number of errors returned is as expected,
     * given that the provided set of clashing bookings was found.
     * It also tests that bookings from the series of bookings being updated are not counted as clashing bookings.
     *
     * @param string $startDate
     * @param string $endDate
     * @param Booking\Model $existingBooking
     * @param Collection $conflictingBookings
     * @param int    $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSeriesValidateForConflicts')]
    public function testSeriesValidateForConflicts(
        string $startDate,
        string $endDate,
        Booking\Model $existingBooking,
        Collection $conflictingBookings,
        int $expected
    ) {
        $mockMapper = $this->getMockBuilder(BookingMapper::class)->getMock();
        $mockMapper
            ->method('findForResource')
            ->willReturn($conflictingBookings);

        $mockValidation = $this->createValidation(new User(), $mockMapper, new StatusMapper(), new TermMapper());

        $resource = new Item\Model();
        $resource->setId(123);

        /** @var Validation $mockValidation */
        $errors = $mockValidation->validateForClashes($resource, new DateTime($startDate), new DateTime($endDate), $existingBooking);
        $errorsToCount = $mockValidation->getAllErrorStringsAsFlatArray($errors);

        $this->assertCount($expected, $errorsToCount);
    }

    /**
     * Data provider for testGetNewStatusIdForBooking
     *
     * @return array
     */
    public static function providerGetNewStatusIdForBooking()
    {
        return [
            'valid booking, no clash, open resource' => [
                'startDate' => '2027-01-01T10:00:00+00:00',
                'endDate' => '2027-01-01T11:00:00+00:00',
                'isClash' => false,
                'moderationType' => Item\ModerationType\Model::TYPE_OPEN,
                'userIsModerator' => false,
                'userIsAuthor' => false,
                'expectedStatus' => Booking\Status\Model::STATUS_APPROVED,
            ],
            'valid booking, no clash, moderated resource, user is moderator and author' => [
                'startDate' => '2027-01-01T10:00:00+00:00',
                'endDate' => '2027-01-01T11:00:00+00:00',
                'isClash' => false,
                'moderationType' => Item\ModerationType\Model::TYPE_MODERATED,
                'userIsModerator' => true,
                'userIsAuthor' => true,
                'expectedStatus' => Booking\Status\Model::STATUS_APPROVED,
            ],
            'valid booking, no clash, moderated resource, user is not moderator' => [
                'startDate' => '2027-01-01T10:00:00+00:00',
                'endDate' => '2027-01-01T11:00:00+00:00',
                'isClash' => false,
                'moderationType' => Item\ModerationType\Model::TYPE_MODERATED,
                'userIsModerator' => false,
                'userIsAuthor' => false,
                'expectedStatus' => Booking\Status\Model::STATUS_PENDING,
            ],
            'booking with clash, clashes allowed' => [
                'startDate' => '2027-01-01T10:00:00+00:00',
                'endDate' => '2027-01-01T11:00:00+00:00',
                'isClash' => true,
                'moderationType' => Item\ModerationType\Model::TYPE_OPEN,
                'userIsModerator' => false,
                'userIsAuthor' => false,
                'expectedStatus' => Booking\Status\Model::STATUS_PENDING,
                'allowClashBookings' => true,
            ],
            'booking with clash, clashes not allowed' => [
                'startDate' => '2027-01-01T10:00:00+00:00',
                'endDate' => '2027-01-01T11:00:00+00:00',
                'isClash' => true,
                'moderationType' => Item\ModerationType\Model::TYPE_OPEN,
                'userIsModerator' => false,
                'userIsAuthor' => false,
                'expectedStatus' => Booking\Status\Model::STATUS_REJECTED,
                'allowClashBookings' => false,
            ],
            'booking in past' => [
                'startDate' => '2020-01-01T10:00:00+00:00',
                'endDate' => '2020-01-01T11:00:00+00:00',
                'isClash' => false,
                'moderationType' => Item\ModerationType\Model::TYPE_OPEN,
                'userIsModerator' => false,
                'userIsAuthor' => false,
                'expectedStatus' => Booking\Status\Model::STATUS_REJECTED,
            ],
            'booking too short' => [
                'startDate' => '2027-01-01T10:00:00+00:00',
                'endDate' => '2027-01-01T10:01:00+00:00',
                'isClash' => false,
                'moderationType' => Item\ModerationType\Model::TYPE_OPEN,
                'userIsModerator' => false,
                'userIsAuthor' => false,
                'expectedStatus' => Booking\Status\Model::STATUS_REJECTED,
                'minReservationTime' => 'PT1H',
            ],
            'booking too long' => [
                'startDate' => '2027-01-01T10:00:00+00:00',
                'endDate' => '2027-01-01T15:00:00+00:00',
                'isClash' => false,
                'moderationType' => Item\ModerationType\Model::TYPE_OPEN,
                'userIsModerator' => false,
                'userIsAuthor' => false,
                'expectedStatus' => Booking\Status\Model::STATUS_REJECTED,
                'maxReservationTime' => 'PT2H',
            ],
        ];
    }

    /**
     * Test getNewStatusIdForBooking function
     *
     * @param string $startDate
     * @param string $endDate
     * @param bool $isClash
     * @param int $moderationType
     * @param bool $userIsModerator
     * @param bool $userIsAuthor
     * @param int $expectedStatus
     * @param bool $allowClashBookings
     * @param string|null $minReservationTime
     * @param string|null $maxReservationTime
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGetNewStatusIdForBooking')]
    public function testGetNewStatusIdForBooking(
        string $startDate,
        string $endDate,
        bool $isClash,
        int $moderationType,
        bool $userIsModerator,
        bool $userIsAuthor,
        int $expectedStatus,
        bool $allowClashBookings = true,
        ?string $minReservationTime = null,
        ?string $maxReservationTime = null
    ): void {
        // Create user
        $user = new User();
        $user->setId(123);

        $validation = $this->createValidation($user);

        // Create resource with moderation type
        $resource = new Item\Model();
        $resource->setId(456);
        $moderationTypeModel = new Item\ModerationType\Model();
        $moderationTypeModel->setId($moderationType);
        $resource->setModerationType($moderationTypeModel);
        $resource->setAllowClashBookings($allowClashBookings);
        $resource->setMinLeadTime(Interval::createFromDateInterval(new DateInterval('PT0H')));

        if ($minReservationTime) {
            $resource->setMinReservationTime(
                Interval::createFromDateInterval(new DateInterval($minReservationTime))
            );
        } else {
            $resource->setMinReservationTime(Interval::createFromDateInterval(new DateInterval('PT0H')));
        }

        if ($maxReservationTime) {
            $resource->setMaxReservationTime(
                Interval::createFromDateInterval(new DateInterval($maxReservationTime))
            );
        } else {
            $resource->setMaxReservationTime(Interval::createFromDateInterval(new DateInterval('PT0H')));
        }

        // Mock isUserModerator to avoid database calls
        $resource = $this->getMockBuilder(Item\Model::class)
            ->onlyMethods(['isUserModerator'])
            ->getMock();
        $resource->method('isUserModerator')->willReturn($userIsModerator);
        $resource->setId(456);
        $resource->setModerationType($moderationTypeModel);
        $resource->setAllowClashBookings($allowClashBookings);
        $resource->setMinLeadTime(Interval::createFromDateInterval(new DateInterval('PT0H')));

        if ($minReservationTime) {
            $resource->setMinReservationTime(
                Interval::createFromDateInterval(new DateInterval($minReservationTime))
            );
        } else {
            $resource->setMinReservationTime(Interval::createFromDateInterval(new DateInterval('PT0H')));
        }

        if ($maxReservationTime) {
            $resource->setMaxReservationTime(
                Interval::createFromDateInterval(new DateInterval($maxReservationTime))
            );
        } else {
            $resource->setMaxReservationTime(Interval::createFromDateInterval(new DateInterval('PT0H')));
        }

        // Create event
        $event = new Event();
        $event->setStartDateTime(new \Alaress_Date_Format($startDate, \Alaress_Date_Format::FORMAT_RFC3339_W3C));
        $event->setEndDateTime(new \Alaress_Date_Format($endDate, \Alaress_Date_Format::FORMAT_RFC3339_W3C));

        // Set author
        $author = new User();
        $author->setId($userIsAuthor ? 123 : 999);
        $event->setAuthor($author);

        // Create booking
        $booking = new Booking\Model();
        $booking->setResource($resource);
        $booking->setEvent($event);

        // Test
        $result = $validation->getNewStatusIdForBooking($booking, $isClash, $user);
        $this->assertEquals($expectedStatus, $result);
    }
}
