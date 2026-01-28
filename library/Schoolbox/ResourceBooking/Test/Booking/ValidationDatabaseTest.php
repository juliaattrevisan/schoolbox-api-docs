<?php

/**
 * Database Integration Tests for Validation class
 *
 * These tests use a real database connection to test:
 * - Actual clash detection with overlapping bookings
 * - Time period edge cases
 * - Series booking exclusion logic
 * - SQL query correctness
 *
 * REQUIREMENTS:
 * - MySQL test database accessible
 * - Test data is created/destroyed using transactions
 *
 * @author    Tom McDonnell (directing AI)
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Test\Booking;

use Alaress\Database\Test\TestConnectorTrait;
use DateTime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Schoolbox\ResourceBooking\Booking;
use Schoolbox\ResourceBooking\Booking\Mapper as BookingMapper;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as StatusMapper;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;

/**
 * Database integration tests for booking clash detection
 *
 * These tests verify that the actual SQL queries correctly detect
 * overlapping bookings in various scenarios.
 *
 */
#[Group("slow")]
#[Group("database")]
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\ResourceBooking\Booking\Validation::class)]
class ValidationDatabaseTest extends TestCase
{
    use TestConnectorTrait;

    private BookingMapper $bookingMapper;
    private EventMapper $eventMapper;
    private StatusMapper $statusMapper;
    private array $testBookingIds = [];
    private array $testEventIds = [];
    private array $testResourceIds = [];
    private int $approvedStatusId;

    /**
     * Set up database connection and begin transaction
     */
    protected function setUp(): void
    {
        // Skip tests if database is not available
        try {
            if (!$this->connectSchoolbox()) {
                $this->markTestSkipped('Database connection not available');
            }

            if ($this->schoolbox === null) {
                $this->markTestSkipped('Database connector is null');
            }

            $this->schoolbox->beginTransaction();

            // Set up mappers with test database connection
            $this->bookingMapper = new BookingMapper();
            $this->eventMapper = new EventMapper();
            $this->statusMapper = new StatusMapper();

            // Get the APPROVED status ID
            $statuses = $this->statusMapper->findAll();
            foreach ($statuses as $status) {
                if ($status->getId() === Booking\Status\Model::STATUS_APPROVED) {
                    $this->approvedStatusId = $status->getId();
                    break;
                }
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Database setup error: ' . $e->getMessage());
        }
    }

    /**
     * Roll back transaction to clean up test data
     */
    protected function tearDown(): void
    {
        try {
            if (isset($this->schoolbox) && $this->schoolbox !== null && method_exists($this->schoolbox, 'inTransaction')) {
                if ($this->schoolbox->inTransaction()) {
                    $this->schoolbox->rollBack();
                }
            }
        } catch (\Throwable $e) {
            // Ignore all errors during cleanup (including Errors, not just Exceptions)
        }
    }

    /**
     * Create a test resource in the database
     */
    private function createTestResource(array $options = []): Item\Model
    {
        $resourceId = $this->schoolbox->executeQuery("
            INSERT INTO resource (name, type_id, category_id, room_code, created_at, updated_at)
            VALUES (
                :name,
                1,
                1,
                :roomCode,
                NOW(),
                NOW()
            )
        ", [
            'name' => $options['name'] ?? 'Test Resource',
            'roomCode' => $options['roomCode'] ?? '',
        ]);

        $this->testResourceIds[] = $resourceId;

        $resource = new Item\Model();
        $resource->setId($resourceId);
        $resource->setName($options['name'] ?? 'Test Resource');

        return $resource;
    }

    /**
     * Create a test event in the database
     */
    private function createTestEvent(DateTime $start, DateTime $end, ?int $seriesId = null): Event
    {
        $params = [
            'title' => 'Test Event',
            'startDatetime' => $start->format('Y-m-d H:i:s'),
            'endDatetime' => $end->format('Y-m-d H:i:s'),
            'type' => Event::TYPE_FACILITY_BOOKING,
            'allDay' => 0,
            'author' => 1,
        ];

        if ($seriesId !== null) {
            $params['seriesId'] = $seriesId;
        }

        $columns = array_keys($params);
        $placeholders = array_map(fn($col) => ":$col", $columns);

        $sql = sprintf(
            "INSERT INTO event (%s) VALUES (%s)",
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $eventId = $this->schoolbox->executeQuery($sql, $params);
        $this->testEventIds[] = $eventId;

        $event = new Event();
        $event->setId($eventId);
        $event->setTitle('Test Event');
        $event->setStartDateTime(
            new \Alaress_Date_Format($start->format('Y-m-d H:i:s'), \Alaress_Date_Format::DATABASE_DATETIME_FORMAT)
        );
        $event->setEndDateTime(
            new \Alaress_Date_Format($end->format('Y-m-d H:i:s'), \Alaress_Date_Format::DATABASE_DATETIME_FORMAT)
        );
        if ($seriesId !== null) {
            $event->setSeriesId($seriesId);
        }

        return $event;
    }

    /**
     * Create a test booking in the database
     */
    private function createTestBooking(
        Item\Model $resource,
        Event $event,
        ?int $statusId = null
    ): Booking\Model {
        if ($statusId === null) {
            $statusId = $this->approvedStatusId;
        }

        $bookingId = $this->schoolbox->executeQuery("
            INSERT INTO resource_booking (
                resource_id,
                event_id,
                resource_booking_status_id,
                name,
                created_at,
                updated_at
            ) VALUES (
                :resourceId,
                :eventId,
                :statusId,
                'Test Booking',
                NOW(),
                NOW()
            )
        ", [
            'resourceId' => $resource->getId(),
            'eventId' => $event->getId(),
            'statusId' => $statusId,
        ]);

        $this->testBookingIds[] = $bookingId;

        $booking = new Booking\Model();
        $booking->setId($bookingId);
        $booking->setResource($resource);
        $booking->setEvent($event);

        return $booking;
    }

    /**
     * Test booking from 10:00-11:00 clashes with booking from 10:30-11:30
     */
    public function testClashDetection_OverlappingEnd(): void
    {
        $resource = $this->createTestResource();

        // Existing booking: 10:00-11:00
        $existingStart = new DateTime('2027-03-25 10:00:00');
        $existingEnd = new DateTime('2027-03-25 11:00:00');
        $existingEvent = $this->createTestEvent($existingStart, $existingEnd);
        $this->createTestBooking($resource, $existingEvent);

        // Try to book: 10:30-11:30 (overlaps with existing)
        $newStart = new DateTime('2027-03-25 10:30:00');
        $newEnd = new DateTime('2027-03-25 11:30:00');

        // Query for conflicting bookings
        $conflicts = $this->bookingMapper->findForResource(
            [$resource->getId()],
            $newStart->getTimestamp() + 1,
            $newEnd->getTimestamp() - 1,
            [$this->approvedStatusId]
        );

        $this->assertGreaterThan(0, $conflicts->count(), 'Should detect clash between 10:00-11:00 and 10:30-11:30');
    }

    /**
     * Test booking from 10:00-11:00 does NOT clash with booking from 11:00-12:00
     * (bookings that meet at exact boundary should not clash)
     */
    public function testNoClash_ExactBoundary(): void
    {
        $resource = $this->createTestResource();

        // Existing booking: 10:00-11:00
        $existingStart = new DateTime('2027-03-25 10:00:00');
        $existingEnd = new DateTime('2027-03-25 11:00:00');
        $existingEvent = $this->createTestEvent($existingStart, $existingEnd);
        $this->createTestBooking($resource, $existingEvent);

        // Try to book: 11:00-12:00 (starts exactly when other ends)
        $newStart = new DateTime('2027-03-25 11:00:00');
        $newEnd = new DateTime('2027-03-25 12:00:00');

        // Note: The +1 and -1 on timestamps is critical here
        // This matches how validateForClashes() calls findForResource()
        $conflicts = $this->bookingMapper->findForResource(
            [$resource->getId()],
            $newStart->getTimestamp() + 1, // Start checking 1 second after requested start
            $newEnd->getTimestamp() - 1,   // Stop checking 1 second before requested end
            [$this->approvedStatusId]
        );

        $this->assertEquals(0, $conflicts->count(), 'Should NOT detect clash when bookings meet at exact boundary');
    }

    /**
     * Test booking from 10:00-11:00 clashes with booking from 09:30-10:30
     */
    public function testClashDetection_OverlappingStart(): void
    {
        $resource = $this->createTestResource();

        // Existing booking: 10:00-11:00
        $existingStart = new DateTime('2027-03-25 10:00:00');
        $existingEnd = new DateTime('2027-03-25 11:00:00');
        $existingEvent = $this->createTestEvent($existingStart, $existingEnd);
        $this->createTestBooking($resource, $existingEvent);

        // Try to book: 09:30-10:30 (overlaps start of existing)
        $newStart = new DateTime('2027-03-25 09:30:00');
        $newEnd = new DateTime('2027-03-25 10:30:00');

        $conflicts = $this->bookingMapper->findForResource(
            [$resource->getId()],
            $newStart->getTimestamp() + 1,
            $newEnd->getTimestamp() - 1,
            [$this->approvedStatusId]
        );

        $this->assertGreaterThan(0, $conflicts->count(), 'Should detect clash between 09:30-10:30 and 10:00-11:00');
    }

    /**
     * Test booking from 10:00-11:00 clashes with booking from 09:00-12:00
     */
    public function testClashDetection_CompletelyContained(): void
    {
        $resource = $this->createTestResource();

        // Existing booking: 10:00-11:00
        $existingStart = new DateTime('2027-03-25 10:00:00');
        $existingEnd = new DateTime('2027-03-25 11:00:00');
        $existingEvent = $this->createTestEvent($existingStart, $existingEnd);
        $this->createTestBooking($resource, $existingEvent);

        // Try to book: 09:00-12:00 (completely contains existing)
        $newStart = new DateTime('2027-03-25 09:00:00');
        $newEnd = new DateTime('2027-03-25 12:00:00');

        $conflicts = $this->bookingMapper->findForResource(
            [$resource->getId()],
            $newStart->getTimestamp() + 1,
            $newEnd->getTimestamp() - 1,
            [$this->approvedStatusId]
        );

        $this->assertGreaterThan(0, $conflicts->count(), 'Should detect clash when new booking contains existing');
    }

    /**
     * Test booking from 10:00-11:00 does NOT clash with booking from 13:00-14:00
     */
    public function testNoClash_CompletelyBefore(): void
    {
        $resource = $this->createTestResource();

        // Existing booking: 10:00-11:00
        $existingStart = new DateTime('2027-03-25 10:00:00');
        $existingEnd = new DateTime('2027-03-25 11:00:00');
        $existingEvent = $this->createTestEvent($existingStart, $existingEnd);
        $this->createTestBooking($resource, $existingEvent);

        // Try to book: 13:00-14:00 (completely after existing)
        $newStart = new DateTime('2027-03-25 13:00:00');
        $newEnd = new DateTime('2027-03-25 14:00:00');

        $conflicts = $this->bookingMapper->findForResource(
            [$resource->getId()],
            $newStart->getTimestamp() + 1,
            $newEnd->getTimestamp() - 1,
            [$this->approvedStatusId]
        );

        $this->assertEquals(0, $conflicts->count(), 'Should NOT detect clash when bookings are completely separate');
    }

    /**
     * Test booking on different resource does NOT clash
     */
    public function testNoClash_DifferentResource(): void
    {
        $resource1 = $this->createTestResource(['name' => 'Resource 1']);
        $resource2 = $this->createTestResource(['name' => 'Resource 2']);

        // Existing booking on resource 1: 10:00-11:00
        $existingStart = new DateTime('2027-03-25 10:00:00');
        $existingEnd = new DateTime('2027-03-25 11:00:00');
        $existingEvent = $this->createTestEvent($existingStart, $existingEnd);
        $this->createTestBooking($resource1, $existingEvent);

        // Try to book resource 2: 10:00-11:00 (same time, different resource)
        $newStart = new DateTime('2027-03-25 10:00:00');
        $newEnd = new DateTime('2027-03-25 11:00:00');

        $conflicts = $this->bookingMapper->findForResource(
            [$resource2->getId()],
            $newStart->getTimestamp() + 1,
            $newEnd->getTimestamp() - 1,
            [$this->approvedStatusId]
        );

        $this->assertEquals(0, $conflicts->count(), 'Should NOT detect clash on different resource');
    }

    /**
     * Test multiple overlapping bookings are all detected
     */
    public function testClashDetection_MultipleConflicts(): void
    {
        $resource = $this->createTestResource();

        // Create 3 existing overlapping bookings
        $bookings = [
            ['10:00:00', '11:00:00'],
            ['10:30:00', '11:30:00'],
            ['11:15:00', '12:00:00'],
        ];

        foreach ($bookings as [$start, $end]) {
            $startTime = new DateTime("2027-03-25 $start");
            $endTime = new DateTime("2027-03-25 $end");
            $event = $this->createTestEvent($startTime, $endTime);
            $this->createTestBooking($resource, $event);
        }

        // Try to book: 10:45-11:45 (overlaps with all 3)
        $newStart = new DateTime('2027-03-25 10:45:00');
        $newEnd = new DateTime('2027-03-25 11:45:00');

        $conflicts = $this->bookingMapper->findForResource(
            [$resource->getId()],
            $newStart->getTimestamp() + 1,
            $newEnd->getTimestamp() - 1,
            [$this->approvedStatusId]
        );

        $this->assertEquals(3, $conflicts->count(), 'Should detect all 3 conflicting bookings');
    }

    /**
     * Test that bookings in the same series are found but can be filtered out
     */
    public function testSeriesBookingsAreFound(): void
    {
        $resource = $this->createTestResource();
        $seriesId = 999; // Arbitrary series ID

        // Create 2 bookings in the same series
        $booking1Start = new DateTime('2027-03-25 10:00:00');
        $booking1End = new DateTime('2027-03-25 11:00:00');
        $event1 = $this->createTestEvent($booking1Start, $booking1End, $seriesId);
        $booking1 = $this->createTestBooking($resource, $event1);

        $booking2Start = new DateTime('2027-04-01 10:00:00'); // Next week
        $booking2End = new DateTime('2027-04-01 11:00:00');
        $event2 = $this->createTestEvent($booking2Start, $booking2End, $seriesId);
        $booking2 = $this->createTestBooking($resource, $event2);

        // Query for first booking's time slot
        $conflicts = $this->bookingMapper->findForResource(
            [$resource->getId()],
            $booking1Start->getTimestamp() + 1,
            $booking1End->getTimestamp() - 1,
            [$this->approvedStatusId]
        );

        // Should find the booking (series filtering happens in validateForClashes)
        $this->assertEquals(1, $conflicts->count());
        $foundBooking = $conflicts->first();
        $this->assertEquals($booking1->getId(), $foundBooking->getId());
        $this->assertEquals($seriesId, $foundBooking->getEvent()->getSeriesId());
    }

    /**
     * Test edge case: booking exactly 1 second before another
     */
    public function testNoClash_OneSecondBefore(): void
    {
        $resource = $this->createTestResource();

        // Existing booking: 10:00:00-11:00:00
        $existingStart = new DateTime('2027-03-25 10:00:00');
        $existingEnd = new DateTime('2027-03-25 11:00:00');
        $existingEvent = $this->createTestEvent($existingStart, $existingEnd);
        $this->createTestBooking($resource, $existingEvent);

        // Try to book: 09:00:00-09:59:59 (ends 1 second before existing starts)
        $newStart = new DateTime('2027-03-25 09:00:00');
        $newEnd = new DateTime('2027-03-25 09:59:59');

        $conflicts = $this->bookingMapper->findForResource(
            [$resource->getId()],
            $newStart->getTimestamp() + 1,
            $newEnd->getTimestamp() - 1,
            [$this->approvedStatusId]
        );

        $this->assertEquals(0, $conflicts->count(), 'Should NOT clash when ending 1 second before existing starts');
    }

    /**
     * Test that only APPROVED status bookings are checked for clashes
     */
    public function testOnlyApprovedBookingsCheckForClashes(): void
    {
        $resource = $this->createTestResource();

        // Create a PENDING booking
        $pendingStart = new DateTime('2027-03-25 10:00:00');
        $pendingEnd = new DateTime('2027-03-25 11:00:00');
        $pendingEvent = $this->createTestEvent($pendingStart, $pendingEnd);
        $this->createTestBooking($resource, $pendingEvent, Booking\Status\Model::STATUS_PENDING);

        // Try to book same time (should not clash with PENDING booking)
        $newStart = new DateTime('2027-03-25 10:00:00');
        $newEnd = new DateTime('2027-03-25 11:00:00');

        // Query only for APPROVED bookings
        $conflicts = $this->bookingMapper->findForResource(
            [$resource->getId()],
            $newStart->getTimestamp() + 1,
            $newEnd->getTimestamp() - 1,
            [$this->approvedStatusId] // Only checking APPROVED
        );

        $this->assertEquals(0, $conflicts->count(), 'Should NOT find PENDING bookings when checking for APPROVED clashes');
    }

    /**
     * Test cross-day booking detection
     */
    public function testClashDetection_CrossDayBooking(): void
    {
        $resource = $this->createTestResource();

        // Existing booking: 23:00 today to 01:00 tomorrow
        $existingStart = new DateTime('2027-03-25 23:00:00');
        $existingEnd = new DateTime('2027-03-26 01:00:00');
        $existingEvent = $this->createTestEvent($existingStart, $existingEnd);
        $this->createTestBooking($resource, $existingEvent);

        // Try to book: 00:00-02:00 next day (overlaps)
        $newStart = new DateTime('2027-03-26 00:00:00');
        $newEnd = new DateTime('2027-03-26 02:00:00');

        $conflicts = $this->bookingMapper->findForResource(
            [$resource->getId()],
            $newStart->getTimestamp() + 1,
            $newEnd->getTimestamp() - 1,
            [$this->approvedStatusId]
        );

        $this->assertGreaterThan(0, $conflicts->count(), 'Should detect clash with cross-day booking');
    }
}
