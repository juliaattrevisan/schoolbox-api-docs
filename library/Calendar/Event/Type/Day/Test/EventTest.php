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
     * @param $class
     * @return DayEvent|mixed
     */
    private function makeEvent(string $start, string $end, $class = DayEvent::class)
    {
        $event = new $class();
        $event->setStartTime(Alaress_Date_Format::newDatabaseDate($start));
        $event->setEndTime(Alaress_Date_Format::newDatabaseDate($end));
        return $event;
    }

    /**
     * Helper to make a ResourceBookingEvent with specified booking ID and status
     *
     * @param string $start
     * @param string $end
     * @param int $bookingId
     * @param int $statusId
     * @return ResourceBookingEvent
     */
    private function makeResourceBookingEvent(string $start, string $end, int $bookingId, int $statusId): ResourceBookingEvent
    {
        $event = new ResourceBookingEvent();
        $event->setStartTime(Alaress_Date_Format::newDatabaseDate($start));
        $event->setEndTime(Alaress_Date_Format::newDatabaseDate($end));

        $booking = new BookingModel();
        $booking->setId($bookingId);
        $status = new BookingStatusModel();
        $status->setId($statusId);
        $booking->setStatus($status);

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
        ?int $aCategory = null,
        ?int $bCategory = null
    ): void {
        $a = $this->makeEvent($aStart, $aEnd, $classA);
        $b = $this->makeEvent($bStart, $bEnd, $classB);

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
                null,
                null,
            ],
            'resourcebooking-vs-timetable-non-overlap' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 10:01:00',
                '2025-11-13 11:00:00',
                ResourceBookingEvent::class,
                TimetableEvent::class,
                false,
                null,
                null,
            ],
            'resourcebooking-vs-suds-overlap' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 09:30:00',
                '2025-11-13 10:30:00',
                ResourceBookingEvent::class,
                SudsTimetableEvent::class,
                true,
                null,
                null,
            ],
            'resourcebooking-vs-suds-non-overlap' => [
                '2025-11-13 09:00:00',
                '2025-11-13 10:00:00',
                '2025-11-13 10:01:00',
                '2025-11-13 11:00:00',
                ResourceBookingEvent::class,
                SudsTimetableEvent::class,
                false,
                null,
                null,
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
        bool $expected
    ): void {
        $a = $this->makeResourceBookingEvent($aStart, $aEnd, $aBookingId, $aStatus);
        $b = $this->makeResourceBookingEvent($bStart, $bEnd, $bBookingId, $bStatus);

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
                false,
            ],
            // RB vs Timetable cases will be tested separately
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
        string $timetableClass,
        bool $expected
    ): void {
        $a = $this->makeResourceBookingEvent($aStart, $aEnd, $aBookingId, $aStatus);
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
                TimetableEvent::class,
                false,
            ],
        ];
    }
}
