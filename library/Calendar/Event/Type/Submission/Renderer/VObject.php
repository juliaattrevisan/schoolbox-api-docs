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

class VObject extends Base
{
    /**
     * Inherit default VObject formatting method & properties
     * vCalendar
     * render()
     */
    use VObjectTrait;
    
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
            $vEvent->add('CATEGORIES', $this->getEvent()->getIsAssessment()?'Quiz':'Due Work');

            $this->getTextConverter()->setHtml($this->getEvent()->getDetail());
            $vEvent->add('DESCRIPTION', $this->getTextConverter()->getText());

            $start_time = $this->getEvent()->getStartTime()->getDateTimeObject();
            if (!($start_time instanceof DateTime)) {
                $start_time = new DateTime($this->getEvent()->getStartTime()->getDatabase());
            } else {
                $start_time = clone $start_time;
            }
            $end_time = $this->getEvent()->getEndTime()->getDateTimeObject();
            if (!($end_time instanceof DateTime)) {
                $end_time = new DateTime($this->getEvent()->getEndTime()->getDatabase());
            } else {
                $end_time = clone $end_time;
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


}