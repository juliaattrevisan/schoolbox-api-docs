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

class CapacityAllocation
{
    public const int BOOKING_QUANTITY = 1;

    /**
     * @param iterable<BookingModel> $approvedBookings
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
                'start' => $start,
                'end' => $end,
                'quantity' => $booking->getQuantity(),
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
     * @param array<int, array{startDatetime: Alaress_Date_Format, endDatetime: Alaress_Date_Format, quantity?: int}> $approvedPeriods
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
                'start' => $period['startDatetime'],
                'end' => $period['endDatetime'],
                'quantity' => $period['quantity'] ?? self::BOOKING_QUANTITY,
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
     * @param array<int, ResourceBookingEvent> $events
     * @return array<int, ResourceBookingEvent>
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
            if (
                !$this->periodsOverlap(
                    $candidate->getStartTime(),
                    $candidate->getEndTime(),
                    $event->getStartTime(),
                    $event->getEndTime()
                )
            ) {
                continue;
            }

            $approvedEvents[] = $event;
            $approvedPeriods[] = [
                'start' => $event->getStartTime(),
                'end' => $event->getEndTime(),
                'quantity' => $booking->getQuantity(),
            ];
        }

        if (
            !$this->wouldExceedCapacity(
                $resource,
                $candidate->getStartTime(),
                $candidate->getEndTime(),
                $approvedPeriods,
                $candidateBooking->getQuantity()
            )
        ) {
            return [];
        }

        return $approvedEvents;
    }

    /**
     * @param array<int, array{start: DateTimeInterface|Alaress_Date_Format, end: DateTimeInterface|Alaress_Date_Format, quantity?: int}> $existingPeriods
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
            $this->addPeriodPoints($points, $period['start'], $period['end'], $period['quantity'] ?? self::BOOKING_QUANTITY);
        }

        usort(
            $points,
            static fn (array $a, array $b): int => ($a['time'] <=> $b['time']) ?: ($a['delta'] <=> $b['delta'])
        );

        $allocated = 0;
        foreach ($points as $point) {
            $allocated += $point['delta'];
            if ($allocated > $capacity) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{time: int, delta: int}> $points
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
        $points[] = ['time' => $startTimestamp, 'delta' => $quantity];
        $points[] = ['time' => $endTimestamp, 'delta' => -$quantity];
    }

    private function periodsOverlap(
        Alaress_Date_Format $aStart,
        Alaress_Date_Format $aEnd,
        Alaress_Date_Format $bStart,
        Alaress_Date_Format $bEnd
    ): bool {
        return $aStart->lt($bEnd) && $aEnd->gt($bStart);
    }

    private function timestamp(DateTimeInterface|Alaress_Date_Format $date): int
    {
        return $date instanceof Alaress_Date_Format ? $date->getUnixTimeStamp() : $date->getTimestamp();
    }
}
