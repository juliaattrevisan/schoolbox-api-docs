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
use Schoolbox\Calendar\Relation\CategoryMapper;
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
     * @var CategoryMapper Needed in order to ensure categoryCollection is set on the event,
     *                     which is needed for the main calendar group filters.
     */
    protected CategoryMapper $categoryMapper;

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

        // use the booking times (if they exist) otherwise fall back to the event times
        $event->setStartTime($booking->getBookingStartAt() ?? $eventItem->getStartDateTime());
        $event->setEndTime($booking->getBookingEndAt() ?? $eventItem->getEndDateTime());

        $event->setPublishDate($eventItem->getPublishDateTime());

        $event->setTitle($booking->getResource()->getName());
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

        // This is the variant of the event row to which the resource_booking row is linked.
        // Resource_booking rows can be linked to event rows whose variant is either 'event' or 'booking'.
        $event->setEventVariant($eventItem->getVariant());

        // If the event belongs to a group, it must have a category collection set.
        // Unfortunately we do not know whether the event belongs to a group here without getting the linked categories,
        // because $eventItem->folderCollection will not be hydrated.
        // A category in this context refers to a folder linked to the event via db table `event_folder`.
        // @see library/Calendar->getDayEvents() where this is also done.
        $allRelations = $this->categoryMapper->findByColumn('event_id', $event->getEventId());
        $event->setCategoryCollection($allRelations);

        $event->setOriginalEvent($eventItem);

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

    /**
     * @param  CategoryMapper $categoryMapper
     * @return $this
     */
    public function setCategoryMapper(CategoryMapper $categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
        return $this;
    }

    /**
     * @return CategoryMapper
     */
    public function getCategoryMapper()
    {
        return $this->categoryMapper;
    }
}
