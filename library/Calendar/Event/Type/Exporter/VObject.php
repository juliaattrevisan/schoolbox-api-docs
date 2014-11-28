<?php
/**
 * Calendar_Event_Type_Exporter_VObject
 *
 * Like Calendar_Event_Type_Exporter but works with the
 * \Sabre\VObject\Component\VCalendar class instead.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Property\ICalendar;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Mapper;

class Calendar_Event_Type_Exporter_VObject extends Calendar_Event_Type_Renderer_Abstract
{
    /**
     * @var EventTypeMapper
     */
    protected $eventTypeMapper;

    protected function setup()
    {
        $this->setEventTypeMapper(new EventTypeMapper::getInstance());
    }

    /**
     * @var VCalendar
     */
    protected $vcalendar;

    /**
     * Render this event
     *
     * @param Calendar_Event_Type_Abstract $currentEvent
     * @return Sabre\VObject\Component\Vevent
     */
    public function visit($currentEvent)
    {

        /* Show visible events only, in export
         */
        if ($this->isVisible($currentEvent)) {
            /** @var Sabre\VObject\Component\Vevent $vevent */
            $vevent = $this->vcalendar->create('VEVENT');
            // Generate a UID based on $currentEvent->getEventId()
            $vevent->add('UID', $currentEvent->getEventId() . '/' . $currentEvent->getConnectorId());
            $vevent->add('DTSTAMP', DateTime::createFromFormat('U', $_SERVER['REQUEST_TIME']));
            try {
                $eventType = $this->getEventTypeMapper()->findById($currentEvent->getType());
                $vevent->add('CATEGORIES', $eventType->getType());
            } catch (Mapper\Exception $e) {
                // noop
            }
            $vevent->add('LOCATION', $currentEvent->getLocation());
            $vevent->add('DESCRIPTION', \Scrub::html2text($currentEvent->getDetail()));

            $start_time = $currentEvent->getStartTime()->getDateTimeObject();
            if (!($start_time instanceof DateTime)) {
                $start_time = new DateTime($currentEvent->getStartTime()->getDatabase());
            }
            $end_time = $currentEvent->getEndTime()->getDateTimeObject();
            if (!($end_time instanceof DateTime)) {
                $end_time = new DateTime($currentEvent->getEndTime()->getDatabase());
            }
            if (!$currentEvent->getAllday()) {
                $vevent->add('DTSTART', $start_time);
                $vevent->add('DTEND', $end_time);
            } else {
                $end_time->add(new DateInterval('P1D'));
                $vevent->add('DTSTART', $start_time->format('Ymd'), array('VALUE' => 'DATE'));
                $vevent->add('DTEND', $end_time->format('Ymd'), array('VALUE' => 'DATE'));
            }

            $vevent->add('SUMMARY', $currentEvent->getTitle());

            return $this->getVCalendar()->add($vevent);
        }
    }

    /**
     * @param  \Sabre\VObject\Component\VCalendar $vcalendar
     * @return $this
     */
    public function setVCalendar($vcalendar)
    {
        $this->vcalendar = $vcalendar;
        return $this;
    }

    /**
     * @return \Sabre\VObject\Component\VCalendar
     */
    public function getVCalendar()
    {
        return $this->vcalendar;
    }

    /**
     * @return EventTypeMapper
     */
    public function getEventTypeMapper()
    {
        return $this->eventTypeMapper;
    }

    /**
     * @param  EventTypeMapper $eventTypeMapper
     * @return $this
     */
    public function setEventTypeMapper($eventTypeMapper)
    {
        $this->eventTypeMapper = $eventTypeMapper;
        return $this;
    }
}
