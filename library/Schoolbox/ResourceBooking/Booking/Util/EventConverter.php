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
use Calendar_Event_Type_Day;
use EOTC\Calendar\Event;

class EventConverter {

    /**
     * Converts an instance of EOTC\Calendar\Event into Calendar_Event_Type_Day
     *
     * @param Event $eventItem
     *
     * @return Calendar_Event_Type_Day
     */
    public function convertEventToCalendarEvent(Event $eventItem) {
        /* Convert dates ;(
         */
        $startDateTime = new \Alaress_Date_Format($eventItem->getStartDate() . ' ' . $eventItem->getStartTime(), 'y-m-d h:i:s');
        $eventItem->setStartDateTime($startDateTime);

        $endDateTime = new \Alaress_Date_Format($eventItem->getEndDate() . ' ' . $eventItem->getEndTime(), 'y-m-d h:i:s');
        $eventItem->setEndDateTime($endDateTime);

        /* Accumulate campuses for this event
         */
//        $eventItem->setCampusCollection($this->findCampuses($eventItem->getId()));

        /* For filters
         */
//        $campusIds = array();

//        foreach($eventItem->getCampusCollection() as $currentCampus) {
//            $campusIds[] = $currentCampus->getId();
//        }

        /* Translate an \EOTC\Event type into a
         * Calendar_Event_Type_Abstract type
         */
        $event = new Calendar_Event_Type_Day();
//        $event->setAuthorName($eventItem->getAuthor()->getFullname());
        $event->setAuthorName($eventItem->getAuthor()->formatName());
        $event->setAuthorId($eventItem->getAuthor()->getId());
        $event->setEventId($eventItem->getId());
        $event->setStartTime($eventItem->getStartDateTime());
        $event->setEndTime($eventItem->getEndDateTime());
//        $event->setCampusList(implode(',', $campusIds));

        /* EOTC Events are always of the type "EOTC" for usage
         * in calendar filters
         */
//        $event->setType($eventItem->getEventType()->getId());
        $event->setNote($eventItem->getNote());
        $event->setAccess($eventItem->getAccess());
//        $event->setEventType($eventItem->getEventType()->getType());
//        $event->setColour($eventItem->getEventType()->getColor());
        $event->setDetail($eventItem->getDetail());
        $event->setLocation($eventItem->getLocation());
        $event->setAllDay($eventItem->getAllDay());

//        /* If you have write access to the event. Only superusers
//         * and authors can modify an event
//         */
//        if ($_SESSION['user']->isSuperuser() || $_SESSION['user']->getId() == $event->getAuthorId()) {
//            $event->setIsEditable(true);
//        }
//        else {
//            $event->setIsEditable(false);
//        }

//        $event->setAttendance(0);
//        $event->setCompleted(0);
        return $event;
    }

}
