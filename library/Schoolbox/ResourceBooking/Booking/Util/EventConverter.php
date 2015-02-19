<?php
/**
 * Converter for mapper events into calendar events.
 *
 * @todo move this somewhere out of the booking module
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$ Why is this even here?
 * @stolenFrom  Steve Chan <steve.chan@alaress.com.au>
 */

namespace Schoolbox\ResourceBooking\Booking\Util;

use Alaress_Date_Format;
use Calendar_Event_Type_ResourceBooking;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventType;
use Schoolbox\ResourceBooking\Booking;

class EventConverter {

    /**
     * The event type for bookings.
     *
     * @var EventType
     */
    protected $bookingEventType;

    /**
     * Converts an instance of Schoolbox\Calendar\Event into Calendar_Event_Type_Abstract
     *
     * @param Event         $eventItem
     * @param Booking\Model $booking
     *
     * @return Calendar_Event_Type_ResourceBooking
     */
    public function convertEventToCalendarEvent(Event $eventItem, Booking\Model $booking) {
        /* Convert dates ;(
         */
        $startDateTime = new \Alaress_Date_Format($eventItem->getStartDate() . ' ' . $eventItem->getStartTime(), 'y-m-d H:i:s');
        $eventItem->setStartDateTime($startDateTime);

        $endDateTime = new \Alaress_Date_Format($eventItem->getEndDate() . ' ' . $eventItem->getEndTime(), 'y-m-d H:i:s');
        $eventItem->setEndDateTime($endDateTime);

        /* Translate a \Schoolbox\Event type into a
         * Calendar_Event_Type_Abstract type
         */
        $event = new \Calendar_Event_Type_ResourceBooking();
        $event->setBooking($booking);
        // Should be able to set owner ID too, but for now that has to be
        // referenced from the booking object.
        $event->setAuthorName($eventItem->getAuthor()->formatName());
        $event->setAuthorId($eventItem->getAuthor()->getId());
        $event->setEventId($eventItem->getId());
        $event->setStartTime($eventItem->getStartDateTime());
        $event->setEndTime($eventItem->getEndDateTime());
        $event->setPublishDate($eventItem->getPublishDateTime());

        $event->setTitle($booking->getName());
        $event->setAccess($eventItem->getAccess());

        // Set the event's type and color if there's a type for bookings
        $eventType = $this->getBookingEventType();
        if ($eventType instanceof EventType) {
            $event->setType($eventType->getId());
            $event->setColour($eventType->getColor());
        }

        $event->setDetail($eventItem->getDetail());
        $event->setLocation($eventItem->getLocation());
        $event->setAllDay($eventItem->getAllDay());
        // Resource booking events: not editable in the conventional way
        $event->setIsEditable(false);
        return $event;
    }

    /**
     * @param  EventType $bookingEventType
     * @return $this
     */
    public function setBookingEventType($bookingEventType) {
        $this->bookingEventType = $bookingEventType;
        return $this;
    }

    /**
     * @return EventType
     */
    public function getBookingEventType() {
        return $this->bookingEventType;
    }

}
