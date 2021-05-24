<?php

/**
 * Converter for mapper events into calendar events.
 *
 * @todo move this somewhere out of the booking module
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 * @stolenFrom  Steve Chan <steve.chan@alaress.com.au>
 */

namespace Schoolbox\ResourceBooking\Booking\Util;

use Calendar\Event\Type\ResourceBooking\Event as ResourceBookingEvent;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventType;
use Schoolbox\ResourceBooking\Booking;

class EventConverter
{

    /**
     * The event type for bookings.
     *
     * @var EventType
     */
    protected $bookingEventType;

    /**
     * Converts an instance of Schoolbox\Calendar\Event into an EventInterface descendant
     *
     * @param Event $eventItem
     * @param Booking\Model $booking
     *
     * @return ResourceBookingEvent
     */
    public function convertEventToCalendarEvent(Event $eventItem, Booking\Model $booking)
    {

        /* Translate a \Schoolbox\Event type into an EventInterface descendant */

        $event = new ResourceBookingEvent();
        $event->setBooking($booking);

        // Should be able to set owner ID too, but for now that has to be referenced from the booking object.

        $event->setAuthorName($eventItem->getAuthor()->formatName());
        $event->setAuthorId($eventItem->getAuthor()->getId());
        $event->setEventId($eventItem->getId());
        $event->setStartTime($eventItem->getStartDateTime());
        $event->setEndTime($eventItem->getEndDateTime());
        $event->setPublishDate($eventItem->getPublishDateTime());

        $event->setTitle($booking->getName());
        $event->setAccess($eventItem->getAccess());

        // Set the event's type and color if there's a type for bookings

        if ($booking->getStatus()->isApproved()) {
            $event->setColour('#8CE059');
        }

        if ($booking->getStatus()->isPending()) {
            $event->setColour('#FAAE32');
        }

        $event->setDetail($booking->getNotes());
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
    public function setBookingEventType($bookingEventType)
    {
        $this->bookingEventType = $bookingEventType;
        return $this;
    }

    /**
     * @return EventType
     */
    public function getBookingEventType()
    {
        return $this->bookingEventType;
    }
}
