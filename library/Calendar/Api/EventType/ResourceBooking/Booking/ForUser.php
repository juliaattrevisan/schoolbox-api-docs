<?php

/**
 * Event source - displays all bookings for a single user.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Calendar\Event\Collection;

class Calendar_Api_EventType_ResourceBooking_Booking_ForUser extends Calendar_Api_EventType_ResourceBooking_Base
{

    /**
     * Get day events from this data source
     *
     * @param int $startTimestamp
     * @param int $endTimestamp
     *
     * @return Collection
     */
    public function getEvents($startTimestamp, $endTimestamp)
    {
        // Get all items out of booking mapper that I am the owner of.
        $bookings = $this->getBookingMapper()->findForOwner(
            $this->getUser()->getId(),
            $startTimestamp,
            $endTimestamp,
            $this->getStatus()
        );
        $collection = new Collection();
        foreach ($bookings as $booking) {
            $calendarEvent = $this->getEventConverter()->convertEventToCalendarEvent(
                $booking->getEvent(),
                $booking
            );
            $calendarEvent->setConnectorId($this->getConnectorId());
            $collection->add($calendarEvent);
        }
        return $collection;
    }
}
