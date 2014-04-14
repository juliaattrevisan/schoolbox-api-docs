<?php
/**
 * Event type for resources that this user has booked.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\ResourceBooking\Booking;

class Calendar_Api_EventType_ResourceBooking_Booking_Default extends Calendar_Api_EventType_Abstract {

    /**
     * A mapper that fetches bookings from the database
     */

    /**
     * Constructor
     *
     * @param $user
     * @param $config
     */
    public function __construct($user, $config) {
        $this->setUser($user);
    }

    /**
     * Get day events from this data source
     *
     * @param int $startTimestamp
     * @param int $endTimestamp
     *
     * @return Calendar_Event_Collection
     */
    public function getEvents($startTimestamp, $endTimestamp) {

        $bookingMapper = Booking\Mapper::getInstance();

        // Get all items out of booking mapper that I am the owner of.
        $bookings = $bookingMapper->findForOwner(
            $this->getUser()->getId(),
            $startTimestamp,
            $endTimestamp
        );
        $converter  = new Booking\Util\EventConverter();
        $collection = new Calendar_Event_Collection();
        foreach ($bookings as $booking) {
            $calendarEvent = $converter->convertEventToCalendarEvent($booking->getEvent(), $booking);
            $calendarEvent->setConnectorId($this->getConnectorId());
            $collection->add($calendarEvent);
        }
        return $collection;
    }

    /**
     * Find a particular event from this source
     *
     * @param $eventId
     * @return Calendar_Event_Collection
     */
    public function getEvent($eventId) {
//        return $this->getCalendarHandler()->getEvent($eventId);
    }

}
