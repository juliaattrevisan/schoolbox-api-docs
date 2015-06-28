<?php
/**
 * This is the vcal renderer for submission events
 * it provides support functions for determining how to render a day event
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

namespace Calendar\Event\Type\Submission\Renderer;

use Calendar\Event\Type\VObjectBase;
use DateTimeZone;
use DateInterval;
use DateTime;
use Sabre\VObject\Component\VEvent;

class VObject extends VObjectBase
{
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
            /** @var VEvent $vevent */
            $vevent = $this->vcalendar->create('VEVENT');
            // Generate a UID based on $this->getEvent()->getEventId()
            $vevent->add('UID', $this->getEvent()->getEventId() . '/' . $this->getEvent()->getConnectorId());
            $vevent->add('CATEGORIES', $this->getEvent()->getIsAssessment()?'Quiz':'Due Work');

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


}