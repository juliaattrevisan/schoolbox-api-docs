<?php

declare(strict_types=1);

namespace Schoolbox\ResourceBooking\Booking;

use Alaress_Date_Format;
use Calendar\Event\Type\ResourceBooking\Event as ResourceBookingEvent;
use DateTime;
use DateTimeInterface;
use Schoolbox\ResourceBooking\Booking\Model as BookingModel;
use Schoolbox\ResourceBooking\Booking\Status\Model as StatusModel;
use Schoolbox\ResourceBooking\Item\Model as ResourceModel;

/**
 * Calculates resource booking capacity usage for proposed and existing allocations.
 *
 * Each booking is treated as a time period that contributes its requested quantity
 * to the resource allocation total. A proposed booking exceeds capacity when the
 * running allocation total is greater than the resource capacity at any point in
 * the combined timeline.
 */
class CapacityAllocation
{
    /**
     * Default quantity for legacy bookings or allocation periods that do not specify a quantity.
     */
    public const int BOOKING_QUANTITY = 1;

    /**
     * Checks whether a candidate booking would exceed a resource capacity alongside approved bookings.
     *
     * The approved bookings are converted into allocation periods using their booking-specific
     * start and end times where present, falling back to their event times.
     *
     * @param ResourceModel $resource Resource whose capacity is being checked.
     * @param DateTime $candidateStart Proposed booking start.
     * @param DateTime $candidateEnd Proposed booking end.
     * @param iterable<BookingModel> $approvedBookings Existing approved bookings to count against capacity.
     * @param int $candidateQuantity Quantity requested by the proposed booking.
     * @return bool True when the proposed booking would exceed resource capacity.
     */
    public function bookingWouldExceedCapacity(
        ResourceModel $resource,
        DateTime $candidateStart,
        DateTime $candidateEnd,
        iterable $approvedBookings,
        int $candidateQuantity = self::BOOKING_QUANTITY
    ): bool {
        $periods = [];
        foreach ($approvedBookings as $booking) {
            $start = $booking->getBookingStartAt() ?? $booking->getEvent()->getStartDateTime();
            $end = $booking->getBookingEndAt() ?? $booking->getEvent()->getEndDateTime();
            if ($start === null || $end === null) {
                continue;
            }
            $periods[] = [
                "start" => $start,
                "end" => $end,
                "quantity" => $booking->getQuantity(),
            ];
        }

        return $this->wouldExceedCapacity(
            $resource,
            $candidateStart,
            $candidateEnd,
            $periods,
            $candidateQuantity
        );
    }

    /**
     * Checks whether a candidate period would exceed capacity alongside approved allocation periods.
     *
     * This variant is used when callers already have period data rather than full booking models.
     * Periods without an explicit quantity are treated as one booked resource.
     *
     * @param ResourceModel $resource Resource whose capacity is being checked.
     * @param Alaress_Date_Format $candidateStart Proposed allocation start.
     * @param Alaress_Date_Format $candidateEnd Proposed allocation end.
     * @param array<int, array{startDatetime: Alaress_Date_Format, endDatetime: Alaress_Date_Format, quantity?: int}> $approvedPeriods Existing periods to count against capacity.
     * @param int $candidateQuantity Quantity requested by the proposed period.
     * @return bool True when the proposed period would exceed resource capacity.
     */
    public function periodWouldExceedCapacity(
        ResourceModel $resource,
        Alaress_Date_Format $candidateStart,
        Alaress_Date_Format $candidateEnd,
        array $approvedPeriods,
        int $candidateQuantity = self::BOOKING_QUANTITY
    ): bool {
        $periods = [];
        foreach ($approvedPeriods as $period) {
            $periods[] = [
                "start" => $period["startDatetime"],
                "end" => $period["endDatetime"],
                "quantity" => $period["quantity"] ?? self::BOOKING_QUANTITY,
            ];
        }

        return $this->wouldExceedCapacity(
            $resource,
            $candidateStart,
            $candidateEnd,
            $periods,
            $candidateQuantity
        );
    }

    /**
     * Returns approved resource booking events that cause a candidate event to exceed capacity.
     *
     * Only approved bookings for the same resource and overlapping time period are counted. The
     * returned events are the blockers to show in clash-only report views when the candidate
     * booking cannot fit within the resource capacity.
     *
     * @param ResourceBookingEvent $candidate Event being checked for capacity conflicts.
     * @param array<int, ResourceBookingEvent> $events Resource booking events in the report window.
     * @return array<int, ResourceBookingEvent> Approved events that block the candidate event.
     */
    public function getBlockingApprovedBookingEvents(ResourceBookingEvent $candidate, array $events): array
    {
        $candidateBooking = $candidate->getBooking();
        if ($candidateBooking->getStatus()->isRejected()) {
            return [];
        }

        $resource = $candidateBooking->getResource();
        $approvedEvents = [];
        $approvedPeriods = [];
        foreach ($events as $event) {
            $booking = $event->getBooking();
            if ($booking->getId() === $candidateBooking->getId()) {
                continue;
            }
            if ($booking->getResource()->getId() !== $resource->getId()) {
                continue;
            }
            if ($booking->getStatus()->getId() !== StatusModel::STATUS_APPROVED) {
                continue;
            }
            if (!$this->periodsOverlap($candidate->getStartTime(), $candidate->getEndTime(), $event->getStartTime(), $event->getEndTime())) {
                continue;
            }

            $approvedEvents[] = $event;
            $approvedPeriods[] = [
                "start" => $event->getStartTime(),
                "end" => $event->getEndTime(),
                "quantity" => $booking->getQuantity(),
            ];
        }

        if (!$this->wouldExceedCapacity($resource, $candidate->getStartTime(), $candidate->getEndTime(), $approvedPeriods, $candidateBooking->getQuantity())) {
            return [];
        }

        return $approvedEvents;
    }

    /**
     * Performs the capacity calculation by sweeping start and end points in chronological order.
     *
     * End points are sorted before start points at the same timestamp, allowing one booking to end
     * exactly when another starts without consuming capacity at the boundary.
     *
     * @param ResourceModel $resource Resource whose capacity is being checked.
     * @param DateTimeInterface|Alaress_Date_Format $candidateStart Proposed allocation start.
     * @param DateTimeInterface|Alaress_Date_Format $candidateEnd Proposed allocation end.
     * @param array<int, array{start: DateTimeInterface|Alaress_Date_Format, end: DateTimeInterface|Alaress_Date_Format, quantity?: int}> $existingPeriods Existing periods to count against capacity.
     * @param int $candidateQuantity Quantity requested by the proposed allocation.
     * @return bool True when allocation exceeds resource capacity at any point.
     */
    private function wouldExceedCapacity(
        ResourceModel $resource,
        DateTimeInterface|Alaress_Date_Format $candidateStart,
        DateTimeInterface|Alaress_Date_Format $candidateEnd,
        array $existingPeriods,
        int $candidateQuantity = self::BOOKING_QUANTITY
    ): bool {
        $capacity = max(1, $resource->getCapacity());
        $points = [];

        $this->addPeriodPoints($points, $candidateStart, $candidateEnd, $candidateQuantity);
        foreach ($existingPeriods as $period) {
            $this->addPeriodPoints($points, $period["start"], $period["end"], $period["quantity"] ?? self::BOOKING_QUANTITY);
        }

        usort(
            $points,
            static fn (array $a, array $b): int => ($a["time"] <=> $b["time"]) ?: ($a["delta"] <=> $b["delta"])
        );

        $allocated = 0;
        foreach ($points as $point) {
            $allocated += $point["delta"];
            if ($allocated > $capacity) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds start and end sweep-line points for a single allocation period.
     *
     * Invalid or empty periods are ignored. Quantities less than one are normalised to one
     * because a booking always consumes at least one unit of capacity.
     *
     * @param array<int, array{time: int, delta: int}> $points Sweep-line point list to append to.
     * @param DateTimeInterface|Alaress_Date_Format $start Allocation start.
     * @param DateTimeInterface|Alaress_Date_Format $end Allocation end.
     * @param int $quantity Quantity consumed during the allocation period.
     */
    private function addPeriodPoints(
        array &$points,
        DateTimeInterface|Alaress_Date_Format $start,
        DateTimeInterface|Alaress_Date_Format $end,
        int $quantity
    ): void {
        $startTimestamp = $this->timestamp($start);
        $endTimestamp = $this->timestamp($end);

        if ($startTimestamp >= $endTimestamp) {
            return;
        }

        $quantity = max(1, $quantity);
        $points[] = ["time" => $startTimestamp, "delta" => $quantity];
        $points[] = ["time" => $endTimestamp, "delta" => -$quantity];
    }

    /**
     * Checks whether two allocation periods overlap.
     *
     * Periods that only touch at a boundary are not considered overlapping.
     *
     * @param Alaress_Date_Format $aStart First period start.
     * @param Alaress_Date_Format $aEnd First period end.
     * @param Alaress_Date_Format $bStart Second period start.
     * @param Alaress_Date_Format $bEnd Second period end.
     * @return bool True when the periods overlap.
     */
    private function periodsOverlap(
        Alaress_Date_Format $aStart,
        Alaress_Date_Format $aEnd,
        Alaress_Date_Format $bStart,
        Alaress_Date_Format $bEnd
    ): bool {
        return $aStart->lt($bEnd) && $aEnd->gt($bStart);
    }

    /**
     * Converts supported date value objects to Unix timestamps for capacity calculations.
     *
     * @param DateTimeInterface|Alaress_Date_Format $date Date value to convert.
     * @return int Unix timestamp for the supplied date.
     */
    private function timestamp(DateTimeInterface|Alaress_Date_Format $date): int
    {
        return $date instanceof Alaress_Date_Format ? $date->getUnixTimeStamp() : $date->getTimestamp();
    }
}
