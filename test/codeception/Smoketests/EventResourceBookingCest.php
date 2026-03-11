<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace Tests\Smoketests;

use Schoolbox\ResourceBooking\Item\ModerationType;
use Tests\Support\IntegrationTester;
use Tests\Support\Payload;

/**
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