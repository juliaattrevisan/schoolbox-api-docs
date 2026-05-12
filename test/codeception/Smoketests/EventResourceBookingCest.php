<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Smoketests;

use Schoolbox\ResourceBooking\Item\ModerationType;
use Schoolbox\ResourceBooking\Booking\Status\Model as BookingStatus;
use Tests\Support\IntegrationTester;
use Tests\Support\Payload;

/**
 * This class contains tests for the resource-booking-related functions in @see Calendar/Event/Controller.php.
 *
 * Tests:
 * * Create a series of events with bookings
 * * Edit a booking on a single event in a series
 * * Edit a booking on all events in a series
 * * Delete a booking from a single event in a series
 * * Delete a booking from all events in a series
 *
 * @noinspection PhpUnused
 */
class EventResourceBookingCest
{
    /**
     * Hook method to execute prior to each test
     * @param IntegrationTester $i
     */
    public function _before(IntegrationTester $i): void
    {
        $i->loginAdmin();
        $i->stopFollowingRedirects();
    }

    /**
     * Helper: Creates a resource (moderation type 'open') and a series of 3 daily all-day events
     * (2076-03-11, 12, 13), with the resource booked on each event.
     *
     * @param IntegrationTester $i
     * @return array The created resource and series events, keyed by 'resource' and 'seriesEvents'.
     * @throws \Exception
     */
    private function createResourceAndSeriesOfEventsWithBookings(IntegrationTester $i): array
    {
        $resourceMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $eventMapper = \Schoolbox\Calendar\EventMapper::getInstance();

        $i->amGoingTo('create a resource with moderation type open');
        $resourceTitle = 'Test resource for series at ' . microtime(true);
        $resourcePayload = new Payload\ResourceBooking\Resource\Create();
        $resourcePayload->setResourceName($resourceTitle);
        $resourcePayload->setResourceCat('3'); // Support Services
        $resourcePayload->setModerationTypeId((string)ModerationType\Model::TYPE_OPEN);
        $i->sendRequest($resourcePayload);
        $i->seeResponseCodeIs(303);
        $resource = $resourceMapper->findOneByColumn('name', $resourceTitle);
        $i->comment('The new resource has id ' . $resource->getId());

        $i->amGoingTo('create a series of 3 daily all-day events (2076-03-11, 12, 13) with a resource booking on each');
        $eventTitle = 'Test series event at ' . microtime(true);
        $eventPayload = new Payload\Calendar\Event\Create(1, 1);
        $eventPayload->setTitle($eventTitle);
        $eventPayload->setFormParam('startDate', date('d/m/Y', strtotime('2076-03-11')));
        $eventPayload->setFormParam('endDate', date('d/m/Y', strtotime('2076-03-11')));
        $eventPayload->setFormParam('repeat', 'daily');
        $eventPayload->setFormParam('repeat_until', date('d/m/Y', strtotime('2076-03-13')));
        $eventPayload->setFormParam('resourceIds[0]', (string)$resource->getId());
        $i->sendRequest($eventPayload);
        $i->seeResponseCodeIs(302);

        $anyEvent = $eventMapper->findOneByColumn('title', $eventTitle);
        $seriesEvents = $eventMapper->getAllInSeries($anyEvent->getSeriesId())->reindex();
        $i->comment('Created series id ' . $anyEvent->getSeriesId() . ' containing ' . count($seriesEvents) . ' events');

        return ['resource' => $resource, 'seriesEvents' => $seriesEvents];
    }

    /**
     * Helper: Creates a capacity-2 resource with moderation type 'open'.
     *
     * @param IntegrationTester $i
     * @param bool $allowClashBookings
     * @return \Schoolbox\ResourceBooking\Item\Model
     */
    private function createCapacityTwoOpenResource(
        IntegrationTester $i,
        bool $allowClashBookings
    ): \Schoolbox\ResourceBooking\Item\Model {
        $resourceMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();

        $resourceTitle = 'Capacity 2 event resource at ' . microtime(true);
        $resourcePayload = new Payload\ResourceBooking\Resource\Create();
        $resourcePayload->setResourceName($resourceTitle);
        $resourcePayload->setResourceCat('3'); // Support Services
        $resourcePayload->setModerationTypeId((string)ModerationType\Model::TYPE_OPEN);
        $resourcePayload->setAllowClashBookings($allowClashBookings);
        $resourcePayload->setCapacity('2');
        $i->sendRequest($resourcePayload);
        $i->seeResponseCodeIs(303);

        $resource = $resourceMapper->findOneByColumn('name', $resourceTitle);
        $i->comment('The new capacity-2 resource has id ' . $resource->getId());
        $i->assertEquals(2, $resource->getCapacity(), 'The resource has capacity 2');
        $i->assertEquals(
            $allowClashBookings,
            $resource->getAllowClashBookings(),
            'Property allow_clash_bookings has the expected value'
        );

        return $resource;
    }

    /**
     * Helper: Creates a timed event with one linked resource booking and returns the booking.
     *
     * @param IntegrationTester $i
     * @param \Schoolbox\ResourceBooking\Item\Model $resource
     * @param string $title
     * @param string $startDateTime
     * @param string $endDateTime
     * @return \Schoolbox\ResourceBooking\Booking\Model
     */
    private function createEventWithResourceBooking(
        IntegrationTester $i,
        \Schoolbox\ResourceBooking\Item\Model $resource,
        string $title,
        string $startDateTime,
        string $endDateTime
    ): \Schoolbox\ResourceBooking\Booking\Model {
        $eventMapper = \Schoolbox\Calendar\EventMapper::getInstance();
        $bookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();

        $eventPayload = new Payload\Calendar\Event\Create(1, 1);
        $eventPayload->setTitle($title);
        $eventPayload->setFormParam('allDay', '0');
        $eventPayload->setFormParam('startDate', $startDateTime);
        $eventPayload->setFormParam('endDate', $endDateTime);
        $eventPayload->setFormParam('repeat_until', substr($startDateTime, 0, 10));
        $eventPayload->setFormParam('resourceIds[0]', (string)$resource->getId());
        $i->sendRequest($eventPayload);
        $i->seeResponseCodeIs(302);

        $event = $eventMapper->findOneByColumn('title', $title);
        $booking = $bookingMapper->findOneByColumnList([
            'event_id' => $event->getId(),
            'resource_id' => $resource->getId(),
        ]);
        $i->comment('Created event ' . $event->getId() . ' with booking ' . $booking->getId());

        return $booking;
    }

    /**
     * Create a series of 3 daily events with a resource booking via the 'Create Event' form, and verify that
     * a booking is created for each event in the series.
     * Tests postCreate() in library/Schoolbox/Calendar/Event/Controller.php.
     *
     * @param IntegrationTester $i
     * @return void
     * @throws \Exception
     */
    public function createSeriesOfEventsWithBookings(IntegrationTester $i): void
    {
        $bookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();

        ['resource' => $resource, 'seriesEvents' => $seriesEvents] =
            $this->createResourceAndSeriesOfEventsWithBookings($i);

        $i->amGoingTo('verify that 3 events were created in the series');
        $i->assertCount(3, $seriesEvents, 'There are 3 events in the series');

        // Verify that each event in the series has exactly one booking for the resource.
        $i->amGoingTo('verify that each event in the series has a booking for the resource');
        foreach ($seriesEvents as $seriesEvent) {
            $eventDate = $seriesEvent->getStartDateTime()->getDatabaseDate();
            $bookingsForEvent = $bookingMapper->findByColumn('event_id', $seriesEvent->getId());
            $i->assertCount(
                1,
                $bookingsForEvent,
                "Event on $eventDate has exactly 1 booking"
            );
            $bookingsForEventAndResource = $bookingMapper->findByColumnList([
                'event_id' => $seriesEvent->getId(),
                'resource_id' => $resource->getId(),
            ]);
            $i->assertCount(
                1,
                $bookingsForEventAndResource,
                "Event on $eventDate has a booking for the expected resource"
            );
        }
    }

    /**
     * Create two events at the same time with bookings for the same capacity-2 resource, and verify both bookings
     * are approved.
     *
     * @param IntegrationTester $i
     * @return void
     * @throws \Exception
     */
    public function addBookingsToEventsAllowsCapacityTwo(IntegrationTester $i): void
    {
        $resource = $this->createCapacityTwoOpenResource($i, false);

        $firstBooking = $this->createEventWithResourceBooking(
            $i,
            $resource,
            'Capacity two event 1 ' . microtime(true),
            '2076-04-01T10:00:00+00:00',
            '2076-04-01T11:00:00+00:00'
        );
        $secondBooking = $this->createEventWithResourceBooking(
            $i,
            $resource,
            'Capacity two event 2 ' . microtime(true),
            '2076-04-01T10:00:00+00:00',
            '2076-04-01T11:00:00+00:00'
        );

        $i->assertEquals(
            BookingStatus::STATUS_APPROVED,
            $firstBooking->getStatus()->getId(),
            'The first booking is approved'
        );
        $i->assertEquals(
            BookingStatus::STATUS_APPROVED,
            $secondBooking->getStatus()->getId(),
            'The second overlapping booking is approved while capacity remains available'
        );
    }

    /**
     * Create two approved overlapping bookings for a capacity-2 resource, then verify a third overlapping event-linked
     * booking is rejected when clashes are not allowed.
     *
     * @param IntegrationTester $i
     * @return void
     * @throws \Exception
     */
    public function addBookingsToEventsRejectsThirdWhenCapacityTwoAndClashesAreNotAllowed(IntegrationTester $i): void
    {
        $resource = $this->createCapacityTwoOpenResource($i, false);

        $this->createEventWithResourceBooking(
            $i,
            $resource,
            'Capacity two approved event 1 ' . microtime(true),
            '2076-04-02T10:00:00+00:00',
            '2076-04-02T11:00:00+00:00'
        );
        $this->createEventWithResourceBooking(
            $i,
            $resource,
            'Capacity two approved event 2 ' . microtime(true),
            '2076-04-02T10:00:00+00:00',
            '2076-04-02T11:00:00+00:00'
        );
        $thirdBooking = $this->createEventWithResourceBooking(
            $i,
            $resource,
            'Capacity two rejected event ' . microtime(true),
            '2076-04-02T10:00:00+00:00',
            '2076-04-02T11:00:00+00:00'
        );

        $i->assertEquals(
            BookingStatus::STATUS_REJECTED,
            $thirdBooking->getStatus()->getId(),
            'The third overlapping booking is rejected once capacity is full'
        );
    }

    /**
     * Create two approved overlapping bookings for a capacity-2 resource, then verify a third overlapping event-linked
     * booking is pending when clashes are allowed.
     *
     * @param IntegrationTester $i
     * @return void
     * @throws \Exception
     */
    public function addBookingsToEventsMakesThirdPendingWhenCapacityTwoAndClashesAreAllowed(IntegrationTester $i): void
    {
        $resource = $this->createCapacityTwoOpenResource($i, true);

        $this->createEventWithResourceBooking(
            $i,
            $resource,
            'Capacity two allowed clash event 1 ' . microtime(true),
            '2076-04-04T10:00:00+00:00',
            '2076-04-04T11:00:00+00:00'
        );
        $this->createEventWithResourceBooking(
            $i,
            $resource,
            'Capacity two allowed clash event 2 ' . microtime(true),
            '2076-04-04T10:00:00+00:00',
            '2076-04-04T11:00:00+00:00'
        );
        $thirdBooking = $this->createEventWithResourceBooking(
            $i,
            $resource,
            'Capacity two pending clash event ' . microtime(true),
            '2076-04-04T10:00:00+00:00',
            '2076-04-04T11:00:00+00:00'
        );

        $i->assertEquals(
            BookingStatus::STATUS_PENDING,
            $thirdBooking->getStatus()->getId(),
            'The third overlapping booking is pending once capacity is full and clashes are allowed'
        );
    }

    /**
     * Edit an event-linked booking into a period where two approved bookings already consume all capacity,
     * and verify the edited booking status is recalculated on the persisted booking.
     *
     * @param IntegrationTester $i
     * @return void
     * @throws \Exception
     */
    public function editBookingUsesCapacityAwareStatusForUpdatedBooking(IntegrationTester $i): void
    {
        $bookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();
        $resource = $this->createCapacityTwoOpenResource($i, false);

        $this->createEventWithResourceBooking(
            $i,
            $resource,
            'Capacity two edit approved event 1 ' . microtime(true),
            '2076-04-03T10:00:00+00:00',
            '2076-04-03T11:00:00+00:00'
        );
        $this->createEventWithResourceBooking(
            $i,
            $resource,
            'Capacity two edit approved event 2 ' . microtime(true),
            '2076-04-03T10:00:00+00:00',
            '2076-04-03T11:00:00+00:00'
        );
        $bookingToEdit = $this->createEventWithResourceBooking(
            $i,
            $resource,
            'Capacity two edit pending event ' . microtime(true),
            '2076-04-03T12:00:00+00:00',
            '2076-04-03T13:00:00+00:00'
        );

        $i->assertEquals(
            BookingStatus::STATUS_APPROVED,
            $bookingToEdit->getStatus()->getId(),
            'The booking to edit starts as approved before it clashes'
        );

        $editUrl = '/calendar/event/' . $bookingToEdit->getEvent()->getId() .
            '/editBooking/' . $bookingToEdit->getId();
        $editPayload = new Payload\Base('POST', $editUrl, $editUrl);
        $editPayload->setFormParam('startDatetime', '2076-04-03T10:00:00+11:00');
        $editPayload->setFormParam('endDatetime', '2076-04-03T11:00:00+11:00');
        $editPayload->setFormParam('notes', 'Updated into a full capacity clash at ' . microtime(true));
        $editPayload->setFormParam('efficacy', \Schoolbox\Calendar\EventRecurrenceMapper::EFFICACY_SELF);
        $i->sendRequest($editPayload);

        $i->seeResponseCodeIs(200);
        $i->canSeeResponseContainsJson(['success' => true, 'nRowsAffected' => 1]);

        $updatedBooking = $bookingMapper->findById($bookingToEdit->getId());
        $i->assertEquals(
            BookingStatus::STATUS_REJECTED,
            $updatedBooking->getStatus()->getId(),
            'The edited booking is rejected when capacity is full and clashes are not allowed'
        );
    }

    /**
     * Edit the booking on the middle event in a series using efficacy=self, and verify that only that
     * booking's notes are updated, while bookings on the other events in the series are unchanged.
     * Tests editBooking() in library/Schoolbox/Calendar/Event/Controller.php.
     *
     * @param IntegrationTester $i
     * @return void
     * @throws \Exception
     */
    public function editBookingOnSingleEventInSeries(IntegrationTester $i): void
    {
        $this->editBookingOnSeriesOfEventsHelper($i, \Schoolbox\Calendar\EventRecurrenceMapper::EFFICACY_SELF);
    }

    /**
     * Edit the booking on all events in a series using efficacy=series, and verify that all bookings'
     * notes are updated.
     * Tests editBooking() in library/Schoolbox/Calendar/Event/Controller.php.
     *
     * @param IntegrationTester $i
     * @return void
     * @throws \Exception
     */
    public function editBookingOnEntireSeries(IntegrationTester $i): void
    {
        $this->editBookingOnSeriesOfEventsHelper($i, \Schoolbox\Calendar\EventRecurrenceMapper::EFFICACY_SERIES);
    }

    /**
     * Helper: shared logic for editBookingOnSingleEventInSeries and editBookingOnEntireSeries.
     *
     * Sets up a resource and a series of 3 events with bookings, edits the booking on the middle event
     * (2076-03-12) using the given efficacy, then verifies the notes on all bookings in the series.
     *
     * @param IntegrationTester $i
     * @param string $efficacy
     * @return void
     * @throws \Exception
     */
    private function editBookingOnSeriesOfEventsHelper(IntegrationTester $i, string $efficacy): void
    {
        $bookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();

        ['resource' => $resource, 'seriesEvents' => $seriesEvents] =
            $this->createResourceAndSeriesOfEventsWithBookings($i);

        // The series events are ordered by start_datetime ASC, so index 1 is the middle event (2076-03-12).
        /** @var \Schoolbox\Calendar\Event $middleEvent */
        $middleEvent = $seriesEvents[1];
        $i->assertEquals(
            '2076-03-12',
            $middleEvent->getStartDateTime()->getDatabaseDate(),
            'The middle event is on 2076-03-12 as expected'
        );
        $i->comment('The middle event has id ' . $middleEvent->getId());

        $middleBooking = $bookingMapper->findOneByColumnList([
            'event_id' => $middleEvent->getId(),
            'resource_id' => $resource->getId(),
        ]);
        $i->comment('The middle event booking has id ' . $middleBooking->getId());

        // Send the editBooking request for the middle event's booking.
        $newNotes = 'Updated notes at ' . microtime(true);
        $i->amGoingTo(
            'edit the booking on the middle event with efficacy=' . $efficacy .
            ' and new notes "' . $newNotes . '"'
        );
        $editUrl = '/calendar/event/' . $middleEvent->getId() . '/editBooking/' . $middleBooking->getId();
        $editPayload = new Payload\Base('POST', $editUrl, $editUrl);
        $editPayload->setFormParam('startDatetime', '2076-03-12T01:00:00+00:00');
        $editPayload->setFormParam('endDatetime', '2076-03-12T02:00:00+00:00');
        $editPayload->setFormParam('notes', $newNotes);
        $editPayload->setFormParam('efficacy', $efficacy);
        $i->sendRequest($editPayload);

        $isSeriesEdit = ($efficacy === \Schoolbox\Calendar\EventRecurrenceMapper::EFFICACY_SERIES);
        $expectedNRowsAffected = $isSeriesEdit ? 3 : 1;
        $i->amGoingTo('check the response is 200 and nRowsAffected=' . $expectedNRowsAffected);
        $i->seeResponseCodeIs(200);
        $i->canSeeResponseContainsJson(['success' => true, 'nRowsAffected' => $expectedNRowsAffected]);

        // Verify the notes on each booking in the series.
        $i->amGoingTo('verify notes on each booking in the series');
        foreach ($seriesEvents as $seriesEvent) {
            $eventDate = $seriesEvent->getStartDateTime()->getDatabaseDate();
            $booking = $bookingMapper->findOneByColumnList([
                'event_id' => $seriesEvent->getId(),
                'resource_id' => $resource->getId(),
            ]);

            if ($isSeriesEdit || $seriesEvent->getId() === $middleEvent->getId()) {
                $i->assertEquals(
                    $newNotes,
                    $booking->getNotes(),
                    "Booking on event $eventDate has the updated notes"
                );
            } else {
                $i->assertNotEquals(
                    $newNotes,
                    $booking->getNotes(),
                    "Booking on event $eventDate still has the original notes (not updated)"
                );
            }
        }
    }

    /**
     * Delete the booking from the middle event in a series using efficacy=self, and verify that only that
     * booking is deleted, while bookings on the other events in the series remain.
     * Tests deleteBooking() in library/Schoolbox/Calendar/Event/Controller.php.
     *
     * @param IntegrationTester $i
     * @return void
     * @throws \Exception
     */
    public function deleteBookingFromSingleEventInSeries(IntegrationTester $i): void
    {
        $this->deleteBookingFromSeriesOfEventsHelper($i, \Schoolbox\Calendar\EventRecurrenceMapper::EFFICACY_SELF);
    }

    /**
     * Delete the booking from all events in a series using efficacy=series, and verify that all bookings
     * in the series are deleted.
     * Tests deleteBooking() in library/Schoolbox/Calendar/Event/Controller.php.
     *
     * @param IntegrationTester $i
     * @return void
     * @throws \Exception
     */
    public function deleteBookingFromEntireSeries(IntegrationTester $i): void
    {
        $this->deleteBookingFromSeriesOfEventsHelper($i, \Schoolbox\Calendar\EventRecurrenceMapper::EFFICACY_SERIES);
    }

    /**
     * Helper: shared logic for deleteBookingFromSingleEventInSeries and deleteBookingFromEntireSeries.
     *
     * Sets up a resource and a series of 3 events with bookings, deletes the booking on the middle event
     * (2076-03-12) using the given efficacy, then verifies which bookings remain in the series.
     *
     * @param IntegrationTester $i
     * @param string $efficacy
     * @return void
     * @throws \Exception
     */
    private function deleteBookingFromSeriesOfEventsHelper(IntegrationTester $i, string $efficacy): void
    {
        $bookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();

        ['resource' => $resource, 'seriesEvents' => $seriesEvents] =
            $this->createResourceAndSeriesOfEventsWithBookings($i);

        // The series events are ordered by start_datetime ASC, so index 1 is the middle event (2076-03-12).
        /** @var \Schoolbox\Calendar\Event $middleEvent */
        $middleEvent = $seriesEvents[1];
        $i->assertEquals(
            '2076-03-12',
            $middleEvent->getStartDateTime()->getDatabaseDate(),
            'The middle event is on 2076-03-12 as expected'
        );
        $i->comment('The middle event has id ' . $middleEvent->getId());

        $middleBooking = $bookingMapper->findOneByColumnList([
            'event_id' => $middleEvent->getId(),
            'resource_id' => $resource->getId(),
        ]);
        $i->comment('The middle event booking has id ' . $middleBooking->getId());

        // Send the deleteBooking request for the middle event's booking.
        $i->amGoingTo(
            'delete the booking on the middle event with efficacy=' . $efficacy
        );
        $deleteUrl = '/calendar/event/' . $middleEvent->getId() . '/deleteBooking/' . $middleBooking->getId();
        $deletePayload = new Payload\Base('DELETE', $deleteUrl, $deleteUrl . '?efficacy=' . urlencode($efficacy));
        $i->sendRequest($deletePayload);

        $isSeriesDelete = ($efficacy === \Schoolbox\Calendar\EventRecurrenceMapper::EFFICACY_SERIES);
        $expectedNRowsAffected = $isSeriesDelete ? 3 : 1;
        $i->amGoingTo('check the response is 200 and nRowsAffected=' . $expectedNRowsAffected);
        $i->seeResponseCodeIs(200);
        $i->canSeeResponseContainsJson(['success' => true, 'nRowsAffected' => $expectedNRowsAffected]);

        // Verify which bookings remain in the series.
        $i->amGoingTo('verify which bookings remain in the series');
        foreach ($seriesEvents as $seriesEvent) {
            $eventDate = $seriesEvent->getStartDateTime()->getDatabaseDate();
            $remainingBookings = $bookingMapper->findByColumnList([
                'event_id' => $seriesEvent->getId(),
                'resource_id' => $resource->getId(),
            ]);

            if ($isSeriesDelete || $seriesEvent->getId() === $middleEvent->getId()) {
                $i->assertCount(
                    0,
                    $remainingBookings,
                    "Booking on event $eventDate has been deleted"
                );
            } else {
                $i->assertCount(
                    1,
                    $remainingBookings,
                    "Booking on event $eventDate still exists (not deleted)"
                );
            }
        }
    }
}
