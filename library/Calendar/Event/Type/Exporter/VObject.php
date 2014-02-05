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
use Sabre\VObject\Component\VEvent;
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

            $properties = array();
            $properties['CATEGORIES'] = array('SCHOOL');

            $e = new VEvent($this->getVCalendar(), '');
            $e->add('CATEGORIES', array('SCHOOL'));

            $start_time = $currentEvent->getStartTime()->getDateTimeObject();
            if (!($start_time instanceof DateTime)) {
                $start_time = new DateTime($currentEvent->getStartTime()->getDatabase());
            }
            $end_time = $currentEvent->getEndTime()->getDateTimeObject();
            if (!($end_time instanceof DateTime)) {
                $end_time = new DateTime($currentEvent->getEndTime()->getDatabase());
            }
            if (!$currentEvent->getAllday()) {

                //
                // Assume that start/end are set up with current timezone.
                // Convert them to UTC.
                $utc_timezone = new DateTimeZone('UTC');
                $start_time->setTimezone($utc_timezone);
                $end_time->setTimezone($utc_timezone);
                $e->add('DTSTART', $start_time);
                $e->add('DTEND',   $end_time);
//                $properties['DTSTART'] = $start_time;
//                $properties['DTEND']   = $end_time;
//                $e->setProperty(
//                    'dtstart',
//                    $start_time->format('Y'),
//                    $start_time->format('m'),
//                    $start_time->format('d'),
//                    $start_time->format('H'),
//                    $start_time->format('i'),
//                    $start_time->format('s'),
//                    'Z'
//                );
//                $e->setProperty(
//                    'dtend',
//                    $end_time->format('Y'),
//                    $end_time->format('m'),
//                    $end_time->format('d'),
//                    $end_time->format('H'),
//                    $end_time->format('i'),
//                    $end_time->format('s'),
//                    'Z'
//                );
            }
            else {
//                $e->setProperty(
//                    'dtstart',
//                    $start_time->format('Y'),
//                    $start_time->format('m'),
//                    $start_time->format('d'),
//                    false,
//                    false,
//                    false,
//                    false,
//                    array('VALUE' => 'DATE')
//                );
                $e->add('DTSTART', array('DATE' => $start_time->format('Ymd')));
                //
                // Schoolbox stores all day end time as the same day.
                // VCAL expects it as start of next, so add a day to SB value.
                $end_time->add(new DateInterval('P1D'));
//                $e->setProperty(
//                    'dtend',
//                    $end_time->format('Y'),
//                    $end_time->format('m'),
//                    $end_time->format('d'),
//                    false,
//                    false,
//                    false,
//                    false,
//                    array('VALUE' => 'DATE')
//                );
//                $properties['DTEND']   = $end_time;
                $e->add('DTEND', array('DATE' => $end_time->format('Ymd')));
            }

            $e->add('SUMMARY', $currentEvent->getNote());
            $this->getVCalendar()->add($e);
            return null;
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
