<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Calendar\Event\Render;

/**
 * Default VObject rendering for all types of Calendar events.
 *
 * @package Calendar\Event\Render
 */
trait VObjectTrait
{
    /**
     * @var VCalendar
     */
    protected $vCalendar;
    
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
            /** @var VEvent $vEvent */
            $vEvent = $this->vCalendar->create('VEVENT');
            // Generate a UID based on $this->getEvent()->getEventId()
            $vEvent->add('UID', $this->getEvent()->getEventId() . '/' . $this->getEvent()->getConnectorId());
            $vEvent->add('CATEGORIES', $this->getEvent()->getEventType());
            $vEvent->add('LOCATION', $this->getEvent()->getLocation());

            $this->getTextConverter()->setHtml($this->getEvent()->getDetail());
            $vEvent->add('DESCRIPTION', $this->getTextConverter()->getText());

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
                $vEvent->add('DTSTART', $start_time);
                $vEvent->add('DTEND', $end_time);
            } else {
                $end_time->add(new DateInterval('P1D'));
                $vEvent->add('DTSTART', $start_time->format('Ymd'), array('VALUE' => 'DATE'));
                $vEvent->add('DTEND', $end_time->format('Ymd'), array('VALUE' => 'DATE'));
            }

            $vEvent->add('SUMMARY', $this->getEvent()->getTitle());

            return $vEvent->serialize();
        }
    }

    /**
     * @param  \Sabre\VObject\Component\VCalendar $vCalendar
     * @return $this
     */
    public function setVCalendar($vCalendar)
    {
        $this->vCalendar = $vCalendar;
        return $this;
    }

    /**
     * @return \Sabre\VObject\Component\VCalendar
     */
    public function getVCalendar()
    {
        return $this->vCalendar;
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