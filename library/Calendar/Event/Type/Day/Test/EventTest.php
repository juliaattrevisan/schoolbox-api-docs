<?php

/**
 * EventTest
 *
 *
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Calendar\Event\Type\Day\Test;

use Alaress_Date_Format;
use Calendar\Event\Type\Day\Event as DayEvent;
use Calendar\Event\Type\Timetable\Event as TimetableEvent;
use Calendar\Event\Type\SudsTimetable\Event as SudsTimetableEvent;
use Calendar\Event\Type\ResourceBooking\Event as ResourceBookingEvent;
use PHPUnit\Framework\TestCase;
use Schoolbox\ResourceBooking\Booking\Model as BookingModel;
use Schoolbox\ResourceBooking\Booking\Status\Model as BookingStatusModel;

/**
 * Tests for Day\Event::isClash
 */
class EventTest extends TestCase
{
    /**
     * Helper to make an event of specified class
     *
     * @param string $start
     * @param string $end
     * @param string $class
     * @param int|null $resourceId
     * @return DayEvent|mixed
     */
    private function makeEvent(string $start, string $end, $class = DayEvent::class, ?int $resourceId = 1)
    {
        $event = new $class();
        $event->setStartTime(Alaress_Date_Format::newDatabaseDate($start));
        $event->setEndTime(Alaress_Date_Format::newDatabaseDate($end));

        if ($class === ResourceBookingEvent::class) {
            // provide a minimal booking with approved status and a resource id so isClash can check resource equality
            $booking = new BookingModel();
            $booking->setStatus((new BookingStatusModel())->setId(BookingStatusModel::STATUS_APPROVED));
            if (!is_null($resourceId)) {
                $resource = new \Schoolbox\ResourceBooking\Item\Model();
                $resource->setId($resourceId);
                $booking->setResource($resource);
            }
            $event->setBooking($booking);
        } else {
            // non-resourcebooking events should carry a resource id for comparison
            if (!is_null($resourceId) && method_exists($event, 'setResourceId')) {
                $event->setResourceId($resourceId);
            }
        }
        return $event;
    }

    /**
     * Helper to make a ResourceBookingEvent with specified booking ID and status
     *
     * @param string $start
     * @param string $end
     * @param int $bookingId
     * @param int $statusId
     * @param int|null $resourceId
     * @return ResourceBookingEvent
     */
    private function makeResourceBookingEvent(string $start, string $end, int $bookingId, int $statusId, ?int $resourceId = 1): ResourceBookingEvent
    {
        $event = new ResourceBookingEvent();
        $event->setStartTime(Alaress_Date_Format::newDatabaseDate($start));
        $event->setEndTime(Alaress_Date_Format::newDatabaseDate($end));

        $booking = new BookingModel();
        $booking->setId($bookingId);
        $status = new BookingStatusModel();
        $status->setId($statusId);
        $booking->setStatus($status);

        // attach the provided resource id so resource comparisons succeed
        $resource = new \Schoolbox\ResourceBooking\Item\Model();
        $resource->setId($resourceId);
        $booking->setResource($resource);

        $event->setBooking($booking);
        return $event;
    }

    /**
     * Test Event clashes, not specifically ResourceBooking or SudsTimetable/Timetable events, just date/times, and maybe category IDs
     *
     * @param string $aStart
     * @param string $aEnd
     * @param string $bStart
     * @param string $bEnd
     * @param string $classA
     * @param string $classB
     * @param bool $expected
     * @param int|null $aCategory
     * @param int|null $bCategory
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsClash')]
    public function testIsClash(
        string $aStart,
        string $aEnd,
        string $bStart,
        string $bEnd,
        string $classA,
        string $classB,
        bool $expected,
        ?int $aResourceId = null,
        ?int $bResourceId = null,
        ?int $aCategory = null,
        ?int $bCategory = null
    ): void {
        $a = $this->makeEvent($aStart, $aEnd, $classA, $aResourceId);
        $b = $this->makeEvent($bStart, $bEnd, $classB, $bResourceId);

        if (!is_null($aCategory)) {
            $a->setCategoryId($aCategory);
        }
        if (!is_null($bCategory)) {
            $b->setCategoryId($bCategory);
        }

        $this->assertSame($expected, $a->isClash($b));
    }

    public static function providerIsClash(): array
    {
        return [
            'overlap-day-events' => [
                '2025-11-13 09:00:00', // aStart
                '2025-11-13 10:00:00', // aEnd
                '2025-11-13 09:30:00', // bStart
                '2025-11-13 10:30:00', // bEnd
                DayEvent::class, // classA
                DayEvent::class, // classB
                true, // clash expected
                1, // aResourceId
                1, // bResourceId
                null, // aCategory
                null, // bCategory
            ],
            'non-overlap-day-events' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 10:01:00',
                '2025-11-13 11:00:00',
                DayEvent::class,
                DayEvent::class,
                false,
                1,
                1,
                null,
                null,
            ],
            'timetable-same-category-no-clash' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                TimetableEvent::class,
                TimetableEvent::class,
                false,
                1,
                1,
                42,
                42,
            ],
            'suds-same-category-clash' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                SudsTimetableEvent::class,
                SudsTimetableEvent::class,
                true,
                1,
                1,
                42,
                42,
            ],
            'suds-both-minus1-overlap-clash' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                SudsTimetableEvent::class,
                SudsTimetableEvent::class,
                true,
                1,
                1,
                -1, // category -1 is the unset category id
                -1, // category -1 is the unset category id
            ],
            'suds-different-category-overlap-clash' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                SudsTimetableEvent::class,
                SudsTimetableEvent::class,
                true,
                1,
                1,
                10,
                11,
            ],
            'suds-non-overlap-no-clash' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 10:01:00',
                '2025-11-13 11:00:00',
                SudsTimetableEvent::class,
                SudsTimetableEvent::class,
                false,
                1,
                1,
                10,
                10,
            ],
            // Cross-object comparisons
            'day-vs-timetable-overlap' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                DayEvent::class,
                TimetableEvent::class,
                true,
                1,
                1,
                null,
                null,
            ],
            'day-vs-timetable-non-overlap' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 10:01:00',
                '2025-11-13 11:00:00',
                DayEvent::class,
                TimetableEvent::class,
                false,
                1,
                1,
                null,
                null,
            ],
            'day-vs-suds-overlap' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                DayEvent::class,
                SudsTimetableEvent::class,
                true,
                1,
                1,
                null,
                null,
            ],
            'timetable-vs-suds-overlap' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                TimetableEvent::class,
                SudsTimetableEvent::class,
                true,
                1,
                1,
                null,
                null,
            ],
            'timetable-vs-suds-non-overlap' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 10:01:00',
                '2025-11-13 11:00:00',
                TimetableEvent::class,
                SudsTimetableEvent::class,
                false,
                1,
                1,
                null,
                null,
            ],
            // ResourceBooking vs Timetable/SudsTimetable
            'resourcebooking-vs-timetable-overlap' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                ResourceBookingEvent::class,
                TimetableEvent::class,
                true,
                1,
                1,
            ],
            'resourcebooking-vs-timetable-non-overlap' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 10:01:00',
                '2025-11-13 11:00:00',
                ResourceBookingEvent::class,
                TimetableEvent::class,
                false,
                1,
                1,
            ],
            'resourcebooking-vs-suds-overlap' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                ResourceBookingEvent::class,
                SudsTimetableEvent::class,
                true,
                1,
                1,
            ],
            'resourcebooking-vs-suds-non-overlap' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 10:01:00',
                '2025-11-13 11:00:00',
                ResourceBookingEvent::class,
                SudsTimetableEvent::class,
                false,
                1,
                1,
            ],
            // resource mismatch should not clash even if times overlap
            'resourcebooking-vs-timetable-different-resource-no-clash' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                ResourceBookingEvent::class,
                TimetableEvent::class,
                false,
                1,
                2,
            ],
            'resourcebooking-vs-suds-different-resource-no-clash' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                ResourceBookingEvent::class,
                SudsTimetableEvent::class,
                false,
                1,
                2,
            ],
        ];
    }

    /**
     * Test ResourceBooking vs ResourceBooking clashes
     *
     * @param string $aStart
     * @param string $aEnd
     * @param string $bStart
     * @param string $bEnd
     * @param int $aBookingId
     * @param int $aStatus
     * @param int $bBookingId
     * @param int $bStatus
     * @param bool $expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerResourceBookingIsClash')]
    public function testResourceBookingIsClash(
        string $aStart,
        string $aEnd,
        string $bStart,
        string $bEnd,
        int $aBookingId,
        int $aStatus,
        int $bBookingId,
        int $bStatus,
        ?int $aResourceId = 1,
        ?int $bResourceId = 1,
        bool $expected
    ): void {
        $a = $this->makeResourceBookingEvent($aStart, $aEnd, $aBookingId, $aStatus, $aResourceId);
        $b = $this->makeResourceBookingEvent($bStart, $bEnd, $bBookingId, $bStatus, $bResourceId);

        $this->assertSame($expected, $a->isClash($b));
    }

    public static function providerResourceBookingIsClash(): array
    {
        return [
            'same-booking-id-no-clash' => [
                '2025-11-13 09:00:00', // aStart
                '2025-11-13 10:00:00', // aEnd
                '2025-11-13 09:30:00', // bStart
                '2025-11-13 10:30:00', // bEnd
                100, // aBookingId
                BookingStatusModel::STATUS_PENDING, // aStatus
                100, // bBookingId
                BookingStatusModel::STATUS_PENDING, // bStatus
                1, // aResourceId
                1, // bResourceId
                false, // clash expected
            ],
            'b-not-pending-no-clash' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                101,
                BookingStatusModel::STATUS_PENDING,
                102,
                BookingStatusModel::STATUS_APPROVED,
                1,
                1,
                true,
            ],
            'b-pending-and-overlap-clash' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                201,
                BookingStatusModel::STATUS_APPROVED,
                202,
                BookingStatusModel::STATUS_PENDING,
                1,
                1,
                true,
            ],
            'both-pending-overlap-clash' => [
                '2025-11-13 08:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                401,
                BookingStatusModel::STATUS_PENDING,
                402,
                BookingStatusModel::STATUS_PENDING,
                1,
                1,
                false,
            ],
            'both-approved-overlap-no-clash' => [
                '2025-11-13 08:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                501,
                BookingStatusModel::STATUS_APPROVED,
                502,
                BookingStatusModel::STATUS_APPROVED,
                1,
                1,
                true,
            ],
            'b-pending-but-non-overlap-no-clash' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 10:01:00',
                '2025-11-13 11:00:00',
                301,
                BookingStatusModel::STATUS_APPROVED,
                302,
                BookingStatusModel::STATUS_PENDING,
                1,
                1,
                false,
            ],
            'overlap-different-resource-no-clash' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                701,
                BookingStatusModel::STATUS_APPROVED,
                702,
                BookingStatusModel::STATUS_APPROVED,
                1, // aResourceId
                2, // bResourceId (different resource)
                false,
            ],
            'b-rejected-no-clash' => [ // rejected bookings do not clash
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                801,
                BookingStatusModel::STATUS_APPROVED,
                802,
                BookingStatusModel::STATUS_REJECTED,
                1,
                1,
                false,
            ],
            'a-rejected-no-clash' => [ // again rejected bookings don't clash
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                901,
                BookingStatusModel::STATUS_REJECTED,
                902,
                BookingStatusModel::STATUS_APPROVED,
                1,
                1,
                false,
            ],
        ];
    }

    /**
     * Specifically test ResourceBookingEvent vs TimetableEvent/SudsTimetableEvent clashes
     *
     * @param string $aStart
     * @param string $aEnd
     * @param string $bStart
     * @param string $bEnd
     * @param int $aBookingId
     * @param int $aStatus
     * @param string $timetableClass
     * @param bool $expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerResourceBookingVsTimetable')]
    public function testResourceBookingVsTimetable(
        string $aStart,
        string $aEnd,
        string $bStart,
        string $bEnd,
        int $aBookingId,
        int $aStatus,
        ?int $aResourceId = 1,
        string $timetableClass,
        bool $expected
    ): void {
        $a = $this->makeResourceBookingEvent($aStart, $aEnd, $aBookingId, $aStatus, $aResourceId);
        $b = $this->makeEvent($bStart, $bEnd, $timetableClass);

        $this->assertSame($expected, $a->isClash($b));
    }

    public static function providerResourceBookingVsTimetable(): array
    {
        return [
            'resourcebooking-pending-vs-timetable-overlap' => [
                '2025-11-13 09:00:00', // aStart
                '2025-11-13 10:00:00', // aEnd
                '2025-11-13 09:30:00', // bStart
                '2025-11-13 10:30:00', // bEnd
                601, // aBookingId
                BookingStatusModel::STATUS_PENDING, // aStatus
                1, // aResourceId
                TimetableEvent::class, // timetableClass
                true, // clash expected
            ],
            'resourcebooking-approved-vs-timetable-overlap' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                602,
                BookingStatusModel::STATUS_APPROVED,
                1,
                TimetableEvent::class,
                true,
            ],
            'resourcebooking-pending-vs-suds-overlap' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                603,
                BookingStatusModel::STATUS_PENDING,
                1,
                SudsTimetableEvent::class,
                true,
            ],
            'resourcebooking-approved-vs-timetable-non-overlap' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 10:01:00',
                '2025-11-13 11:00:00',
                604,
                BookingStatusModel::STATUS_APPROVED,
                1,
                TimetableEvent::class,
                false,
            ],
        ];
    }
}
