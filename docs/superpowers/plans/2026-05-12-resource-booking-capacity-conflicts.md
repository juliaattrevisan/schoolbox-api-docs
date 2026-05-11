# Resource Booking Capacity Conflict Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make resource booking conflict logic capacity-aware using the existing `resource.capacity` field, with each booking consuming implicit quantity `1` and no booking quantity storage, payload, API, or UI changes.

**Architecture:** Add a small allocation helper beside `Booking\Validation` and route all existing clash decisions through it. Existing approved bookings consume one unit; pending and rejected bookings do not consume capacity; timetable room events remain hard blockers. Existing controller status behavior remains intact: capacity exceeded produces the same conflict signal that currently drives reject-or-pending behavior.

**Tech Stack:** PHP 8.4, PHPUnit, Codeception smoke/API tests, existing Schoolbox mapper/model/calendar APIs.

---

## Scope

In scope:

- Capacity-aware conflict checks for resource booking create, update, recurrence, related-resource, calendar-linked add/edit/move, approval, moderation/dashboard/reporting, and notifications.
- Existing `resource.capacity` values of `1`, `2`, `5`, and decreased capacity scenarios.
- Approval-time rechecks.
- Unit and smoke/API tests.

Out of scope:

- Booking quantity fields or storage.
- Frontend booking quantity controls.
- API payload changes for booking quantity.
- Changing already-approved bookings when capacity is decreased.
- Solving concurrent over-allocation across simultaneous requests; document the residual race.

## Files

- Create: `library/Schoolbox/ResourceBooking/Booking/CapacityAllocation.php`
- Create: `library/Schoolbox/ResourceBooking/Test/Booking/CapacityAllocationTest.php`
- Modify: `library/Schoolbox/ResourceBooking/Booking/Validation.php`
- Modify: `library/Schoolbox/ResourceBooking/Test/Booking/ValidationTest.php`
- Modify: `library/Schoolbox/Calendar/Event/Controller.php`
- Modify: `library/Schoolbox/ResourceBooking/Report/Controller.php`
- Modify: `library/Schoolbox/ResourceBooking/Booking/Component/ResourceManagement.php`
- Modify: `library/Schoolbox/ResourceBooking/Booking/Notifier.php` only if comments or notification tests reveal direct binary assumptions not covered by `Validation`.
- Modify: `test/codeception/Smoketests/ResourceBookingCest.php`
- Modify: `test/codeception/Smoketests/EventResourceBookingCest.php`

Do not modify `node_modules/`, `vendor/`, booking payload contracts for quantity, or resource capacity storage/form code already implemented under SA-21790.

---

### Task 1: Add Capacity Allocation Helper

**Files:**
- Create: `library/Schoolbox/ResourceBooking/Booking/CapacityAllocation.php`
- Create: `library/Schoolbox/ResourceBooking/Test/Booking/CapacityAllocationTest.php`

- [ ] **Step 1: Write failing helper tests**

Create `library/Schoolbox/ResourceBooking/Test/Booking/CapacityAllocationTest.php` with focused PHPUnit coverage:

```php
<?php

declare(strict_types=1);

namespace Schoolbox\ResourceBooking\Test\Booking;

use Alaress_Date_Format;
use Calendar\Event\Type\ResourceBooking\Event as ResourceBookingEvent;
use DateTime;
use PHPUnit\Framework\TestCase;
use Schoolbox\Calendar\Event;
use Schoolbox\ResourceBooking\Booking\CapacityAllocation;
use Schoolbox\ResourceBooking\Booking\Model as BookingModel;
use Schoolbox\ResourceBooking\Booking\Status\Model as StatusModel;
use Schoolbox\ResourceBooking\Item\Model as ResourceModel;

#[\PHPUnit\Framework\Attributes\CoversClass(CapacityAllocation::class)]
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
            $this->booking(1, $resource, '2077-01-01T10:00:00+00:00', '2077-01-01T11:00:00+00:00', StatusModel::STATUS_PENDING)
        );
        $pending = $this->resourceBookingEvent(
            $this->booking(2, $resource, '2077-01-01T10:00:00+00:00', '2077-01-01T11:00:00+00:00', StatusModel::STATUS_PENDING)
        );

        $this->assertSame([], $allocation->getBlockingApprovedBookingEvents($candidate, [$candidate, $pending]));
    }

    public function testApprovedEventsBlockPendingCandidateWhenCapacityIsFull(): void
    {
        $resource = (new ResourceModel())->setId(10)->setCapacity(1);
        $allocation = new CapacityAllocation();

        $candidate = $this->resourceBookingEvent(
            $this->booking(1, $resource, '2077-01-01T10:00:00+00:00', '2077-01-01T11:00:00+00:00', StatusModel::STATUS_PENDING)
        );
        $approved = $this->resourceBookingEvent(
            $this->booking(2, $resource, '2077-01-01T10:30:00+00:00', '2077-01-01T11:30:00+00:00', StatusModel::STATUS_APPROVED)
        );

        $this->assertSame([$approved], $allocation->getBlockingApprovedBookingEvents($candidate, [$candidate, $approved]));
    }

    private function booking(
        int $id,
        ResourceModel $resource,
        string $start,
        string $end,
        int $statusId = StatusModel::STATUS_APPROVED
    ): BookingModel {
        $event = (new Event())
            ->setStartDateTime($this->date($start))
            ->setEndDateTime($this->date($end));

        return (new BookingModel())
            ->setId($id)
            ->setResource($resource)
            ->setEvent($event)
            ->setStatus((new StatusModel())->setId($statusId));
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
```

- [ ] **Step 2: Run the new test and verify it fails**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/ResourceBooking/Test/Booking/CapacityAllocationTest.php
```

Expected: FAIL because `Schoolbox\ResourceBooking\Booking\CapacityAllocation` does not exist.

- [ ] **Step 3: Implement the helper**

Create `library/Schoolbox/ResourceBooking/Booking/CapacityAllocation.php`:

```php
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
        iterable $approvedBookings
    ): bool {
        $periods = [];
        foreach ($approvedBookings as $booking) {
            $start = $booking->getBookingStartAt() ?? $booking->getEvent()?->getStartDateTime();
            $end = $booking->getBookingEndAt() ?? $booking->getEvent()?->getEndDateTime();
            if ($start === null || $end === null) {
                continue;
            }
            $periods[] = [
                'start' => $start,
                'end' => $end,
            ];
        }

        return $this->wouldExceedCapacity(
            $resource,
            $candidateStart,
            $candidateEnd,
            $periods
        );
    }

    /**
     * @param array<int, array{startDatetime: Alaress_Date_Format, endDatetime: Alaress_Date_Format}> $approvedPeriods
     */
    public function periodWouldExceedCapacity(
        ResourceModel $resource,
        Alaress_Date_Format $candidateStart,
        Alaress_Date_Format $candidateEnd,
        array $approvedPeriods
    ): bool {
        $periods = [];
        foreach ($approvedPeriods as $period) {
            $periods[] = [
                'start' => $period['startDatetime'],
                'end' => $period['endDatetime'],
            ];
        }

        return $this->wouldExceedCapacity(
            $resource,
            $candidateStart,
            $candidateEnd,
            $periods
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
            if (!$this->periodsOverlap(
                $candidate->getStartTime(),
                $candidate->getEndTime(),
                $event->getStartTime(),
                $event->getEndTime()
            )) {
                continue;
            }

            $approvedEvents[] = $event;
            $approvedPeriods[] = [
                'start' => $event->getStartTime(),
                'end' => $event->getEndTime(),
            ];
        }

        if (!$this->wouldExceedCapacity($resource, $candidate->getStartTime(), $candidate->getEndTime(), $approvedPeriods)) {
            return [];
        }

        return $approvedEvents;
    }

    /**
     * @param array<int, array{start: DateTimeInterface|Alaress_Date_Format, end: DateTimeInterface|Alaress_Date_Format}> $existingPeriods
     */
    private function wouldExceedCapacity(
        ResourceModel $resource,
        DateTimeInterface|Alaress_Date_Format $candidateStart,
        DateTimeInterface|Alaress_Date_Format $candidateEnd,
        array $existingPeriods
    ): bool {
        $capacity = max(1, $resource->getCapacity());
        $points = [];

        $this->addPeriodPoints($points, $candidateStart, $candidateEnd, self::BOOKING_QUANTITY);
        foreach ($existingPeriods as $period) {
            $this->addPeriodPoints($points, $period['start'], $period['end'], self::BOOKING_QUANTITY);
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
```

- [ ] **Step 4: Run helper tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/ResourceBooking/Test/Booking/CapacityAllocationTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add library/Schoolbox/ResourceBooking/Booking/CapacityAllocation.php library/Schoolbox/ResourceBooking/Test/Booking/CapacityAllocationTest.php
git commit -m "Add resource booking capacity allocation helper [SA-21791]"
```

---

### Task 2: Make Validation Capacity-Aware

**Files:**
- Modify: `library/Schoolbox/ResourceBooking/Booking/Validation.php`
- Modify: `library/Schoolbox/ResourceBooking/Test/Booking/ValidationTest.php`

- [ ] **Step 1: Add failing validation cases**

Update `ValidationTest` providers so booking conflict tests include resource capacity and booking periods. Add cases with these expected outcomes:

```php
'capacity 1 with one overlapping approved booking remains a clash' => [
    'capacity' => 1,
    'conflictingBookings' => [
        ['id' => 11, 'startDate' => '2077-01-01T10:30:00+00:00', 'endDate' => '2077-01-01T11:30:00+00:00'],
    ],
    'expected' => 1,
],
'capacity 2 with one overlapping approved booking has no clash' => [
    'capacity' => 2,
    'conflictingBookings' => [
        ['id' => 11, 'startDate' => '2077-01-01T10:30:00+00:00', 'endDate' => '2077-01-01T11:30:00+00:00'],
    ],
    'expected' => 0,
],
'capacity 2 with two simultaneous approved bookings clashes' => [
    'capacity' => 2,
    'conflictingBookings' => [
        ['id' => 11, 'startDate' => '2077-01-01T10:00:00+00:00', 'endDate' => '2077-01-01T11:00:00+00:00'],
        ['id' => 12, 'startDate' => '2077-01-01T10:30:00+00:00', 'endDate' => '2077-01-01T11:30:00+00:00'],
    ],
    'expected' => 1,
],
'capacity 5 with four simultaneous approved bookings has remaining capacity' => [
    'capacity' => 5,
    'conflictingBookings' => [
        ['id' => 11, 'startDate' => '2077-01-01T10:00:00+00:00', 'endDate' => '2077-01-01T11:00:00+00:00'],
        ['id' => 12, 'startDate' => '2077-01-01T10:00:00+00:00', 'endDate' => '2077-01-01T11:00:00+00:00'],
        ['id' => 13, 'startDate' => '2077-01-01T10:00:00+00:00', 'endDate' => '2077-01-01T11:00:00+00:00'],
        ['id' => 14, 'startDate' => '2077-01-01T10:00:00+00:00', 'endDate' => '2077-01-01T11:00:00+00:00'],
    ],
    'expected' => 0,
],
```

Add a helper in `ValidationTest` to create hydrated approved bookings:

```php
private function createApprovedBookingWithPeriod(
    int $id,
    string $startDate,
    string $endDate,
    ?int $seriesId = null
): Booking\Model {
    $event = new Event();
    $event->setStartDateTime(new \Alaress_Date_Format($startDate, \Alaress_Date_Format::FORMAT_RFC3339_W3C));
    $event->setEndDateTime(new \Alaress_Date_Format($endDate, \Alaress_Date_Format::FORMAT_RFC3339_W3C));
    $event->setSeriesId($seriesId);

    return (new Booking\Model())
        ->setId($id)
        ->setEvent($event)
        ->setStatus((new Booking\Status\Model())->setId(Booking\Status\Model::STATUS_APPROVED));
}
```

- [ ] **Step 2: Run validation tests and verify capacity cases fail**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/ResourceBooking/Test/Booking/ValidationTest.php
```

Expected: FAIL for capacity `2` and `5` cases because current validation treats any overlap as conflict.

- [ ] **Step 3: Wire `CapacityAllocation` into `Validation`**

In `Validation.php`, add:

```php
private CapacityAllocation $capacityAllocation;
```

In `__construct()`, after mapper assignments:

```php
$this->capacityAllocation = new CapacityAllocation();
```

Change `validateForClashes()` so approved booking overlaps become an error only when allocation is exhausted:

```php
$conflictingBookings = $this->getConflictingBookings($resource, $startDate, $endDate, $existingBooking);
$startDateKey = $startDate->format(Alaress_Date_Format::DATABASE_DATE_FORMAT);

if ($this->capacityAllocation->bookingWouldExceedCapacity($resource, $startDate, $endDate, $conflictingBookings)) {
    $errors[self::START_PERIOD_ERRORS][$startDateKey][] = self::ERROR_CONFLICT;
}

if (strlen($resource->getRoomCode() ?? '') > 0) {
    $roomEvents = $this->getTimetableRoomEvents($resource->getRoomCode(), $startDate, $endDate);
    if (count($roomEvents)) {
        $errors[self::START_PERIOD_ERRORS][$startDateKey][] = self::ERROR_TIMETABLED_CLASS;
    }
}
```

Change `getConflictingBookingsForSetOfBookings()` to merge overlaps only when the candidate booking would exceed capacity:

```php
$conflictingBookings = $this->getConflictingBookings(
    $booking->getResource(),
    $startDatetime->getDateTimeObject(),
    $endDatetime->getDateTimeObject(),
    $booking,
);

if (
    $this->capacityAllocation->bookingWouldExceedCapacity(
        $booking->getResource(),
        $startDatetime->getDateTimeObject(),
        $endDatetime->getDateTimeObject(),
        $conflictingBookings
    )
) {
    $allConflictingBookings = $allConflictingBookings->merge($conflictingBookings);
}
```

Change `checkForClashesIfResourceBookingsMoved()` so each moved booking calls:

```php
$isClashByBookingId[$movedBooking->getId()] = $this->capacityAllocation->periodWouldExceedCapacity(
    $resourceById[$movedBooking->getResource()->getId()] ?? $movedBooking->getResource(),
    $movedBooking->getBookingStartAt(),
    $movedBooking->getBookingEndAt(),
    $periodsOfApprovedBookingsByResourceId[$movedBooking->getResource()->getId()] ?? [],
);
```

Change `checkForClashesIfResourceBookingsAddedToEvents()` so each event/resource pair calls:

```php
if (
    $this->capacityAllocation->periodWouldExceedCapacity(
        $resource,
        $event->getStartDateTime(),
        $event->getEndDateTime(),
        $periodsOfApprovedBookingsByResourceId[$resourceId] ?? []
    )
) {
    $isClashByResourceIdByEventId[$eventId][$resourceId] = true;
}
```

Change `getClashEvents()` so overlapping approved bookings are returned only when allocation is exceeded; timetable events are still returned whenever present:

```php
$approvedBookings = $this->getConflictingBookings($resource, $startDate, $endDate, $existingBooking);
if ($this->capacityAllocation->bookingWouldExceedCapacity($resource, $startDate, $endDate, $approvedBookings)) {
    $conflictingBookings = $conflictingBookings->merge($approvedBookings);
}
```

- [ ] **Step 4: Update bulk validation providers for capacity**

In `providerCheckForClashesIfResourceBookingsMoved()` and `providerCheckForClashesIfResourceBookingsAddedToEvents()`, add `capacity` to each resource input with default `1`. Add these cases:

```php
'capacity 2 allows a second simultaneous booking' => [
    'capacityByResourceId' => [$resource1Id => 2],
    'approvedBookingPeriods' => [
        $resource1Id => [
            ['startDatetime' => '2077-01-01T10:30:00+00:00', 'endDatetime' => '2077-01-01T11:30:00+00:00'],
        ],
    ],
    'expectedIsClashByBookingId' => [$booking1Id => false],
],
'capacity 2 rejects a third simultaneous booking' => [
    'capacityByResourceId' => [$resource1Id => 2],
    'approvedBookingPeriods' => [
        $resource1Id => [
            ['startDatetime' => '2077-01-01T10:00:00+00:00', 'endDatetime' => '2077-01-01T11:00:00+00:00'],
            ['startDatetime' => '2077-01-01T10:30:00+00:00', 'endDatetime' => '2077-01-01T11:30:00+00:00'],
        ],
    ],
    'expectedIsClashByBookingId' => [$booking1Id => true],
],
```

When creating test resources in those tests, set:

```php
$resource->setCapacity($capacityByResourceId[$resourceId] ?? 1);
```

- [ ] **Step 5: Run validation tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/ResourceBooking/Test/Booking/ValidationTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add library/Schoolbox/ResourceBooking/Booking/Validation.php library/Schoolbox/ResourceBooking/Test/Booking/ValidationTest.php
git commit -m "Make resource booking validation capacity-aware [SA-21791]"
```

---

### Task 3: Update Calendar-Linked Booking Paths

**Files:**
- Modify: `library/Schoolbox/Calendar/Event/Controller.php`
- Modify: `test/codeception/Smoketests/EventResourceBookingCest.php`

- [ ] **Step 1: Add failing smoke coverage for event add/edit capacity**

In `EventResourceBookingCest`, add tests that create a resource with capacity `2`, then:

```php
public function addBookingsToEventsAllowsCapacityTwo(IntegrationTester $i): void
{
    // Create two events at the same time.
    // Add the same capacity-2 resource to both.
    // Assert both bookings are approved.
}

public function addBookingsToEventsRejectsThirdWhenCapacityTwoAndClashesAreNotAllowed(IntegrationTester $i): void
{
    // Create two approved overlapping bookings for a capacity-2 resource.
    // Add the same resource to a third overlapping event.
    // Assert the third booking is rejected.
}

public function editBookingUsesCapacityAwareStatusForUpdatedBooking(IntegrationTester $i): void
{
    // Create a capacity-2 resource and two approved bookings.
    // Edit a third pending booking into their overlapping period.
    // Assert the edited booking is rejected or pending according to allow_clash_bookings.
}
```

Use the existing payload patterns in `createResourceAndSeriesOfEventsWithBookings()` and `editBookingOnSeriesOfEventsHelper()`. Keep the resource create payload using `setCapacity('2')`; do not add booking quantity fields.

- [ ] **Step 2: Run event booking smoke tests and confirm failure**

Run inside `docker/ssh` for final authority:

```bash
./vendor/bin/codecept run Smoketests EventResourceBookingCest
```

Expected before implementation: at least one new capacity expectation fails, and the edit test exposes that `editBooking()` sets status on `$booking` instead of `$bookingToUpdate`.

- [ ] **Step 3: Fix `editBooking()` status assignment**

In `library/Schoolbox/Calendar/Event/Controller.php`, replace the status update loop with:

```php
foreach ($bookingsToUpdate as $bookingToUpdate) {
    $statusId = $this->bookingValidation->getNewStatusIdForBooking(
        $bookingToUpdate,
        $isClashByBookingId[$bookingToUpdate->getId()],
        $this->getUser()
    );
    $bookingToUpdate->setStatus($this->bookingStatusMapper->createModel($statusId));
}
```

No extra calendar add/move branching is needed after Task 2 because `checkForClashesIfResourceBookingsAddedToEvents()` and `checkForClashesIfResourceBookingsMoved()` are capacity-aware.

- [ ] **Step 4: Run event booking tests**

Run:

```bash
./vendor/bin/codecept run Smoketests EventResourceBookingCest
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add library/Schoolbox/Calendar/Event/Controller.php test/codeception/Smoketests/EventResourceBookingCest.php
git commit -m "Apply capacity-aware rules to calendar-linked bookings [SA-21791]"
```

---

### Task 4: Cover Resource Booking Create, Update, Moderation, and Approval

**Files:**
- Modify: `test/codeception/Smoketests/ResourceBookingCest.php`
- Modify: `library/Schoolbox/ResourceBooking/Test/Booking/ValidationTest.php`

- [ ] **Step 1: Add smoke tests for create/update status outcomes**

In `ResourceBookingCest`, add tests with these concrete scenarios:

```php
public function capacityTwoAllowsSecondOverlappingApprovedBooking(IntegrationTester $i): void
{
    // Create open resource with capacity=2 and allow_clash_bookings=false.
    // Create two overlapping bookings.
    // Assert both bookings have STATUS_APPROVED.
}

public function capacityTwoRejectsThirdOverlappingBookingWhenClashesNotAllowed(IntegrationTester $i): void
{
    // Create open resource with capacity=2 and allow_clash_bookings=false.
    // Create two overlapping approved bookings.
    // Create a third overlapping booking.
    // Assert third booking has STATUS_REJECTED.
}

public function capacityTwoCreatesThirdAsPendingWhenClashesAllowed(IntegrationTester $i): void
{
    // Create open resource with capacity=2 and allow_clash_bookings=true.
    // Create two overlapping approved bookings.
    // Create a third overlapping booking.
    // Assert third booking has STATUS_PENDING.
}

public function moderatedResourceWithCapacityAvailableCreatesPending(IntegrationTester $i): void
{
    // Create moderated resource with capacity=5.
    // Create one booking.
    // Assert the booking has STATUS_PENDING because moderation still wins.
}
```

Use future booking dates such as `2077-02-01 10:00-11:00`. Reuse `Payload\ResourceBooking\Resource\BookItem` and resource payload setters.

- [ ] **Step 2: Add approval-time unit coverage**

In `ValidationTest::providerValidateChangeStatus()`, add capacity-aware cases:

```php
'approve pending booking succeeds when capacity remains' => [
    'status' => Booking\Status\Model::STATUS_APPROVED,
    'resourceCapacity' => 2,
    'conflictingBookings' => [
        ['id' => 999, 'startDate' => '2077-01-01T10:30:00+00:00', 'endDate' => '2077-01-01T11:30:00+00:00'],
    ],
    'expectedErrorCount' => 0,
],
'approve pending booking fails when capacity is exhausted' => [
    'status' => Booking\Status\Model::STATUS_APPROVED,
    'resourceCapacity' => 2,
    'conflictingBookings' => [
        ['id' => 999, 'startDate' => '2077-01-01T10:00:00+00:00', 'endDate' => '2077-01-01T11:00:00+00:00'],
        ['id' => 1000, 'startDate' => '2077-01-01T10:30:00+00:00', 'endDate' => '2077-01-01T11:30:00+00:00'],
    ],
    'expectedErrorCount' => 1,
],
```

- [ ] **Step 3: Run focused tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/ResourceBooking/Test/Booking/ValidationTest.php
./vendor/bin/codecept run Smoketests ResourceBookingCest
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add test/codeception/Smoketests/ResourceBookingCest.php library/Schoolbox/ResourceBooking/Test/Booking/ValidationTest.php
git commit -m "Test capacity-aware booking status outcomes [SA-21791]"
```

---

### Task 5: Update Reporting and Dashboard Clash Surfaces

**Files:**
- Modify: `library/Schoolbox/ResourceBooking/Report/Controller.php`
- Modify: `library/Schoolbox/ResourceBooking/Booking/Component/ResourceManagement.php`
- Optionally modify: `library/Calendar/Event/Type/Day/Event.php` comments only if needed.

- [ ] **Step 1: Replace binary booking overlap use in report filtering**

In `Report\Controller`, instantiate the helper before `$clashesOnly` filtering:

```php
$capacityAllocation = new \Schoolbox\ResourceBooking\Booking\CapacityAllocation();
```

Inside each `$resourceEvents` group, partition resource booking events and other events:

```php
$resourceBookingEvents = array_values(array_filter(
    $resourceEvents,
    static fn ($event): bool => $event instanceof \Calendar\Event\Type\ResourceBooking\Event
));
```

For resource booking events, mark only capacity conflicts:

```php
foreach ($resourceBookingEvents as $eventA) {
    $blockingEvents = $capacityAllocation->getBlockingApprovedBookingEvents($eventA, $resourceBookingEvents);
    if (count($blockingEvents) === 0) {
        continue;
    }

    $clashesOnlyList[spl_object_hash($eventA)] = $eventA;
    foreach ($blockingEvents as $blockingEvent) {
        $clashesOnlyList[spl_object_hash($blockingEvent)] = $blockingEvent;
    }
}
```

Keep timetable hard blockers by retaining pairwise `isClash()` only where at least one side is not a resource booking:

```php
if (
    !(
        $eventA instanceof \Calendar\Event\Type\ResourceBooking\Event &&
        $eventB instanceof \Calendar\Event\Type\ResourceBooking\Event
    ) &&
    $eventA->isClash($eventB)
) {
    $clashesOnlyList[$eventAHash] = $eventA;
    $clashesOnlyList[spl_object_hash($eventB)] = $eventB;
}
```

- [ ] **Step 2: Replace dashboard pending clash list logic**

In `ResourceManagement::execute()`, create the helper:

```php
$capacityAllocation = new Booking\CapacityAllocation();
```

For pending events, replace the binary approved-overlap loop with:

```php
$blockingEvents = $capacityAllocation->getBlockingApprovedBookingEvents($eventA, $resourceEvents);
foreach ($blockingEvents as $blockingEvent) {
    $groupedBookings[$resourceID]['bookingEvents'][$eventA->getStartTime()->getDateOnly()]['events'][$eventAHash]['clash'][] = $blockingEvent;
}
```

This keeps pending bookings visible while only showing approved blockers when approving the pending booking would exceed capacity.

- [ ] **Step 3: Add or update tests**

If practical, add unit coverage for `CapacityAllocation::getBlockingApprovedBookingEvents()` rather than brittle controller rendering tests:

```php
public function testCapacityTwoDashboardCandidateHasNoBlockerWithOneApproved(): void
{
    $resource = (new ResourceModel())->setId(10)->setCapacity(2);
    $allocation = new CapacityAllocation();

    $pending = $this->resourceBookingEvent($this->booking(1, $resource, '2077-01-01T10:00:00+00:00', '2077-01-01T11:00:00+00:00', StatusModel::STATUS_PENDING));
    $approved = $this->resourceBookingEvent($this->booking(2, $resource, '2077-01-01T10:00:00+00:00', '2077-01-01T11:00:00+00:00', StatusModel::STATUS_APPROVED));

    $this->assertSame([], $allocation->getBlockingApprovedBookingEvents($pending, [$pending, $approved]));
}
```

- [ ] **Step 4: Run focused PHP tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/ResourceBooking/Test/Booking/CapacityAllocationTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add library/Schoolbox/ResourceBooking/Report/Controller.php library/Schoolbox/ResourceBooking/Booking/Component/ResourceManagement.php library/Schoolbox/ResourceBooking/Test/Booking/CapacityAllocationTest.php
git commit -m "Surface capacity-aware resource booking clashes [SA-21791]"
```

---

### Task 6: Notification Review

**Files:**
- Modify: `library/Schoolbox/ResourceBooking/Booking/Notifier.php` only if direct code changes are needed.
- Test through `library/Schoolbox/ResourceBooking/Test/Booking/ValidationTest.php` and `CapacityAllocationTest.php`.

- [ ] **Step 1: Confirm notifier uses `Validation` only**

Check that `Notifier::notify()` calls:

```php
$this->validation->validateForClashes(...)
$this->validation->getClashEvents(...)
```

Expected: both methods are capacity-aware after Task 2, so the notifier should stop adding "with clashes" for overlaps within capacity and continue adding it for exhausted capacity or timetable blockers.

- [ ] **Step 2: Add a validation-level notification proxy test**

In `ValidationTest`, assert `getClashEvents()` behavior:

```php
public function testGetClashEventsReturnsNoBookingEventsWhenCapacityRemains(): void
{
    // capacity=2, one approved overlapping booking.
    // validateForClashes returns no errors.
    // getClashEvents returns an empty collection.
}

public function testGetClashEventsReturnsApprovedBookingEventsWhenCapacityExhausted(): void
{
    // capacity=2, two simultaneous approved overlapping bookings.
    // validateForClashes returns ERROR_CONFLICT.
    // getClashEvents returns the overlapping approved bookings.
}
```

- [ ] **Step 3: Run validation tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/ResourceBooking/Test/Booking/ValidationTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add library/Schoolbox/ResourceBooking/Test/Booking/ValidationTest.php library/Schoolbox/ResourceBooking/Booking/Notifier.php
git commit -m "Keep booking clash notifications capacity-aware [SA-21791]"
```

If `Notifier.php` is unchanged, omit it from `git add`.

---

### Task 7: Final Verification

**Files:**
- No planned source changes.

- [ ] **Step 1: Run PHP style and static analysis on changed PHP files**

Run:

```bash
./vendor/bin/phpcs library/Schoolbox/ResourceBooking/Booking/CapacityAllocation.php library/Schoolbox/ResourceBooking/Booking/Validation.php library/Schoolbox/Calendar/Event/Controller.php library/Schoolbox/ResourceBooking/Report/Controller.php library/Schoolbox/ResourceBooking/Booking/Component/ResourceManagement.php library/Schoolbox/ResourceBooking/Test/Booking/CapacityAllocationTest.php library/Schoolbox/ResourceBooking/Test/Booking/ValidationTest.php
./vendor/bin/phpstan analyze library/Schoolbox/ResourceBooking/Booking/CapacityAllocation.php library/Schoolbox/ResourceBooking/Booking/Validation.php library/Schoolbox/Calendar/Event/Controller.php library/Schoolbox/ResourceBooking/Report/Controller.php library/Schoolbox/ResourceBooking/Booking/Component/ResourceManagement.php
```

Expected: both commands pass. Fix any new issues in the files changed for this uplift; do not add baseline suppressions for new problems.

- [ ] **Step 2: Run focused unit tests**

Run:

```bash
./vendor/bin/phpunit library/Schoolbox/ResourceBooking/Test/Booking/CapacityAllocationTest.php library/Schoolbox/ResourceBooking/Test/Booking/ValidationTest.php
```

Expected: PASS.

- [ ] **Step 3: Run smoke/API suites for touched flows in Docker**

Run from inside `docker/ssh`:

```bash
./vendor/bin/codecept run Smoketests ResourceBookingCest
./vendor/bin/codecept run Smoketests EventResourceBookingCest
```

Expected: PASS.

- [ ] **Step 4: Run full PHP unit suite in Docker**

Run from inside `docker/ssh`:

```bash
./vendor/bin/phpunit
```

Expected: PASS.

- [ ] **Step 5: Review git diff**

Run:

```bash
git diff --stat
git diff --check
git status --short
```

Expected:

- No whitespace errors.
- Only planned files are modified.
- Unrelated `SA-21767_test_plan.csv` remains untracked and untouched.

- [ ] **Step 6: Commit final fixes if needed**

If verification required fixes, commit them:

```bash
git add <changed-files>
git commit -m "Verify capacity-aware resource booking conflicts [SA-21791]"
```

---

## Residual Risk

Allocation checks still run before writes without a resource-level lock. Two concurrent creates or approvals can both observe remaining capacity and then over-allocate. This plan intentionally keeps that as a documented residual risk because adding transactional locking changes broader write semantics and should be designed separately.

## Self-Review

- Spec coverage: The plan covers implicit quantity `1`, capacity `1/2/5`, pending bookings not consuming capacity, moderated resources staying pending, approval-time rechecks, calendar add/edit/move, reports/dashboard, notifications through `Validation`, and over-capacity visibility after capacity decreases.
- Scope guard: No booking quantity storage, payload fields, or UI are added.
- Type consistency: The helper consistently uses `resource.capacity`, `BookingModel`, `ResourceModel`, `Alaress_Date_Format`, and existing status IDs.
- Placeholder scan: No task uses a placeholder implementation; each task has concrete files, code shape, commands, and expected results.
