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

namespace Calendar\Event\Type;

use Html2Text\Html2Text;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Property\ICalendar;
use DateTimeZone;
use DateInterval;
use DateTime;
use Sabre\VObject\Component\VEvent;

class VObjectBase extends RendererBase
{
    /**
     * @var VCalendar
     */
    protected $vcalendar;

    /**
     * @var Html2Text
     */
    protected $textConverter;


    /**
     * Render this event
     *
     * @return string
     */
    public function render()
    {
        // Output event times as UTC,
        // no client should be able to mess that up.
        $utcTimezone = new DateTimeZone('UTC');
        /* Show visible events only, in export
         */
        if ($this->isVisible()) {
            // Generate a UID based on $this->getEvent()->getEventId()
            /** @var VEvent $vevent */
            $vevent = $this->vcalendar->create(
                'VEVENT',
                ['UID' => $this->getEvent()->getEventId() . '/' . $this->getEvent()->getConnectorId(),]
            );
            $vevent->add('CATEGORIES', $this->getEvent()->getEventType());
            $vevent->add('LOCATION', $this->getEvent()->getLocation());

            $this->getTextConverter()->set_html($this->getEvent()->getDetail());
            $vevent->add('DESCRIPTION', $this->getTextConverter()->get_text());

            $start_time = $this->getEvent()->getStartTime()->getDateTimeObject();
            if (!($start_time instanceof DateTime)) {
                $start_time = new DateTime($this->getEvent()->getStartTime()->getDatabase());
            }
            $end_time = $this->getEvent()->getEndTime()->getDateTimeObject();
            if (!($end_time instanceof DateTime)) {
                $end_time = new DateTime($this->getEvent()->getEndTime()->getDatabase());
            }
            if (!$this->getEvent()->getAllday()) {
                $start_time->setTimezone($utcTimezone);
                $end_time->setTimezone($utcTimezone);
                $vevent->add('DTSTART', $start_time);
                $vevent->add('DTEND', $end_time);
            } else {
                $end_time->add(new DateInterval('P1D'));
                $vevent->add('DTSTART', $start_time->format('Ymd'), array('VALUE' => 'DATE'));
                $vevent->add('DTEND', $end_time->format('Ymd'), array('VALUE' => 'DATE'));
            }

            $vevent->add('SUMMARY', $this->getEvent()->getTitle());

            return $vevent->serialize();
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
     * @return Html2Text
     */
    public function getTextConverter()
    {
        return $this->textConverter;
    }

    /**
     * @param  Html2Text $textConverter
     *
     * @return $this
     */
    public function setTextConverter($textConverter)
    {
        $this->textConverter = $textConverter;
        return $this;
    }
}
