<?php

declare(strict_types=1);

namespace Schoolbox\ResourceBooking\Test\Booking;

use Alaress_Date_Format;
use Calendar\Event\Type\ResourceBooking\Event as ResourceBookingEvent;
use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Schoolbox\Calendar\Event;
use Schoolbox\ResourceBooking\Booking\CapacityAllocation;
use Schoolbox\ResourceBooking\Booking\Model as BookingModel;
use Schoolbox\ResourceBooking\Booking\Status\Model as StatusModel;
use Schoolbox\ResourceBooking\Item\Model as ResourceModel;

#[CoversClass(CapacityAllocation::class)]
class CapacityAllocationTest extends TestCase
{
    public function testCapacityOneKeepsBinaryConflictBehaviour(): void
    {
        $resource = (new ResourceModel())->setId(10)->setCapacity(1);
        $allocation = new CapacityAllocation();

        $this->assertTrue($allocation->bookingWouldExceedCapacity(
            $resource,
            new DateTime('2077-01-01T10:00:00+00:00'),
            new DateTime('2077-01-01T11:00:00+00:00'),
            [$this->booking(1, $resource, '2077-01-01T10:30:00+00:00', '2077-01-01T11:30:00+00:00')]
        ));
    }

    public function testCapacityTwoAllowsSecondOverlappingApprovedBooking(): void
    {
        $resource = (new ResourceModel())->setId(10)->setCapacity(2);
        $allocation = new CapacityAllocation();

        $this->assertFalse($allocation->bookingWouldExceedCapacity(
            $resource,
            new DateTime('2077-01-01T10:00:00+00:00'),
            new DateTime('2077-01-01T11:00:00+00:00'),
            [$this->booking(1, $resource, '2077-01-01T10:30:00+00:00', '2077-01-01T11:30:00+00:00')]
        ));
    }

    public function testBookingQuantityConsumesMultipleCapacitySlots(): void
    {
        $resource = (new ResourceModel())->setId(10)->setCapacity(5);
        $allocation = new CapacityAllocation();
        $approvedBookings = [
            $this->booking(
                1,
                $resource,
                '2077-01-01T10:00:00+00:00',
                '2077-01-01T11:00:00+00:00',
                quantity: 2
            ),
        ];

        $this->assertFalse($allocation->bookingWouldExceedCapacity(
            $resource,
            new DateTime('2077-01-01T10:00:00+00:00'),
            new DateTime('2077-01-01T11:00:00+00:00'),
            $approvedBookings,
            3
        ));
        $this->assertTrue($allocation->bookingWouldExceedCapacity(
            $resource,
            new DateTime('2077-01-01T10:00:00+00:00'),
            new DateTime('2077-01-01T11:00:00+00:00'),
            $approvedBookings,
            4
        ));
    }

    public function testAdditionalApprovedPeriodQuantityConsumesCapacity(): void
    {
        $resource = (new ResourceModel())->setId(10)->setCapacity(3);
        $allocation = new CapacityAllocation();

        $this->assertTrue($allocation->periodWouldExceedCapacity(
            $resource,
            $this->date('2077-01-01T10:00:00+00:00'),
            $this->date('2077-01-01T11:00:00+00:00'),
            [[
                'startDatetime' => $this->date('2077-01-01T10:00:00+00:00'),
                'endDatetime' => $this->date('2077-01-01T11:00:00+00:00'),
                'quantity' => 2,
            ]],
            2
        ));
    }

    public function testCapacityTwoRejectsThirdSimultaneousBooking(): void
    {
        $resource = (new ResourceModel())->setId(10)->setCapacity(2);
        $allocation = new CapacityAllocation();

        $this->assertTrue($allocation->bookingWouldExceedCapacity(
            $resource,
            new DateTime('2077-01-01T10:00:00+00:00'),
            new DateTime('2077-01-01T11:00:00+00:00'),
            [
                $this->booking(1, $resource, '2077-01-01T10:00:00+00:00', '2077-01-01T11:00:00+00:00'),
                $this->booking(2, $resource, '2077-01-01T10:30:00+00:00', '2077-01-01T11:30:00+00:00'),
            ]
        ));
    }

    public function testNonSimultaneousOverlapsDoNotConsumeCapacityAtSameInstant(): void
    {
        $resource = (new ResourceModel())->setId(10)->setCapacity(2);
        $allocation = new CapacityAllocation();

        $this->assertFalse($allocation->bookingWouldExceedCapacity(
            $resource,
            new DateTime('2077-01-01T10:00:00+00:00'),
            new DateTime('2077-01-01T12:00:00+00:00'),
            [
                $this->booking(1, $resource, '2077-01-01T10:00:00+00:00', '2077-01-01T11:00:00+00:00'),
                $this->booking(2, $resource, '2077-01-01T11:00:00+00:00', '2077-01-01T12:00:00+00:00'),
            ]
        ));
    }

    public function testPeriodCapacityUsesSameBoundaryRules(): void
    {
        $resource = (new ResourceModel())->setId(10)->setCapacity(1);
        $allocation = new CapacityAllocation();

        $this->assertFalse($allocation->periodWouldExceedCapacity(
            $resource,
            $this->date('2077-01-01T10:00:00+00:00'),
            $this->date('2077-01-01T11:00:00+00:00'),
            [[
                'startDatetime' => $this->date('2077-01-01T11:00:00+00:00'),
                'endDatetime' => $this->date('2077-01-01T12:00:00+00:00'),
            ]]
        ));
    }

    public function testPendingEventsDoNotBlockDashboardCandidate(): void
    {
        $resource = (new ResourceModel())->setId(10)->setCapacity(1);
        $allocation = new CapacityAllocation();

        $candidate = $this->resourceBookingEvent(
            $this->booking(
                1,
                $resource,
                '2077-01-01T10:00:00+00:00',
                '2077-01-01T11:00:00+00:00',
                StatusModel::STATUS_PENDING
            )
        );
        $pending = $this->resourceBookingEvent(
            $this->booking(
                2,
                $resource,
                '2077-01-01T10:00:00+00:00',
                '2077-01-01T11:00:00+00:00',
                StatusModel::STATUS_PENDING
            )
        );

        $this->assertSame([], $allocation->getBlockingApprovedBookingEvents($candidate, [$candidate, $pending]));
    }

    public function testApprovedEventsBlockPendingCandidateWhenCapacityIsFull(): void
    {
        $resource = (new ResourceModel())->setId(10)->setCapacity(1);
        $allocation = new CapacityAllocation();

        $candidate = $this->resourceBookingEvent(
            $this->booking(
                1,
                $resource,
                '2077-01-01T10:00:00+00:00',
                '2077-01-01T11:00:00+00:00',
                StatusModel::STATUS_PENDING
            )
        );
        $approved = $this->resourceBookingEvent(
            $this->booking(
                2,
                $resource,
                '2077-01-01T10:30:00+00:00',
                '2077-01-01T11:30:00+00:00',
                StatusModel::STATUS_APPROVED
            )
        );

        $this->assertSame([$approved], $allocation->getBlockingApprovedBookingEvents($candidate, [$candidate, $approved]));
    }

    public function testApprovedEventQuantityBlocksPendingCandidateWhenCapacityWouldBeExceeded(): void
    {
        $resource = (new ResourceModel())->setId(10)->setCapacity(3);
        $allocation = new CapacityAllocation();

        $candidate = $this->resourceBookingEvent(
            $this->booking(
                1,
                $resource,
                '2077-01-01T10:00:00+00:00',
                '2077-01-01T11:00:00+00:00',
                StatusModel::STATUS_PENDING,
                2
            )
        );
        $approved = $this->resourceBookingEvent(
            $this->booking(
                2,
                $resource,
                '2077-01-01T10:30:00+00:00',
                '2077-01-01T11:30:00+00:00',
                StatusModel::STATUS_APPROVED,
                2
            )
        );

        $this->assertSame([$approved], $allocation->getBlockingApprovedBookingEvents($candidate, [$candidate, $approved]));
    }

    private function booking(
        int $id,
        ResourceModel $resource,
        string $start,
        string $end,
        int $statusId = StatusModel::STATUS_APPROVED,
        int $quantity = 1
    ): BookingModel {
        $event = (new Event())
            ->setStartDateTime($this->date($start))
            ->setEndDateTime($this->date($end));

        return (new BookingModel())
            ->setId($id)
            ->setResource($resource)
            ->setEvent($event)
            ->setStatus((new StatusModel())->setId($statusId))
            ->setQuantity($quantity);
    }

    private function resourceBookingEvent(BookingModel $booking): ResourceBookingEvent
    {
        $event = new ResourceBookingEvent();
        $event->setBooking($booking);
        $event->setStartTime($booking->getBookingStartAt() ?? $booking->getEvent()->getStartDateTime());
        $event->setEndTime($booking->getBookingEndAt() ?? $booking->getEvent()->getEndDateTime());

        return $event;
    }

    private function date(string $value): Alaress_Date_Format
    {
        return new Alaress_Date_Format($value, Alaress_Date_Format::FORMAT_RFC3339_W3C);
    }
}
