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

class Calendar_Event_Type_Exporter_VObject extends Calendar_Event_Type_Renderer_Abstract {

    /**
     * @var VCalendar
     */
    protected $vcalendar;

    /**
     * Render this event
     *
     * @param Calendar_Event_Type_Abstract $currentEvent
     * @return \vevent
     */
    public function visit($currentEvent) {

        /* Show visible events only, in export
         */
        if ($this->isVisible($currentEvent)) {
            $eventTypeMapper = new \Schoolbox\Calendar\EventTypeMapper();
            $eventType = $eventTypeMapper->findById($currentEvent->getType());

            $e = array();
            // Generate a UID based on $currentEvent->getEventId()
            $e['UID']        = $currentEvent->getEventId().'/'.$currentEvent->getConnectorId();
            $e['DTSTAMP']    = DateTime::createFromFormat('U', $_SERVER['REQUEST_TIME']);
            $e['CATEGORIES'] = $eventType->getType();
            $e['LOCATION'] = $currentEvent->getLocation();

            $start_time = $currentEvent->getStartTime()->getDateTimeObject();
            if (!($start_time instanceof DateTime)) {
                $start_time = new DateTime($currentEvent->getStartTime()->getDatabase());
            }
            $end_time = $currentEvent->getEndTime()->getDateTimeObject();
            if (!($end_time instanceof DateTime)) {
                $end_time = new DateTime($currentEvent->getEndTime()->getDatabase());
            }
            if (!$currentEvent->getAllday()) {
                $e['DTSTART'] = $start_time;
                $e['DTEND']   = $end_time;
            }
            else {
                $end_time->add(new DateInterval('P1D'));
                $e['DTSTART'] = array('DATE' => $start_time->format('Ymd'));
                $e['DTEND']   = array('DATE' => $end_time->format('Ymd'));
            }

            $e['SUMMARY'] = $currentEvent->getTitle();

            return $this->getVCalendar()->add('VEVENT', $e);
        }
    }

    /**
     * @param  \Sabre\VObject\Component\VCalendar $vcalendar
     * @return $this
     */
    public function setVCalendar($vcalendar) {
        $this->vcalendar = $vcalendar;
        return $this;
    }

    /**
     * @return \Sabre\VObject\Component\VCalendar
     */
    public function getVCalendar() {
        return $this->vcalendar;
    }
}
