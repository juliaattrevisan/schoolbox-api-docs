<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Render;

use Calendar\Event\Type\EventInterface;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use \DateTime;
use \DateInterval;
use \DateTimeZone;
use Alaress\Html;

/**
 * Default VObject rendering for all types of Calendar events.
 *
 * Inheriting classes may override selected methods & properties.
 *
 * @package Calendar\Event\Render
 */
trait VObjectTrait
{

    /**
     * @var VCalendar
     */
    private $vCalendar;

    /**
     * @var Html\ToText
     */
    private $textConverter;


    /**
     * Render this event
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function render(): string
    {
        /** @var EventInterface $event */
        $event = $this->getEvent();

        // Output event times as UTC,
        // no client should be able to mess that up.
        $utcTimezone = new DateTimeZone('UTC');

        /** @var VEvent $vEvent */
        $vEvent = $this->getVCalendar()->create('VEVENT');
        // Generate a UID based on $this->getEvent()->getEventId()
        // Only if getEventId is not negative (Google Calendar doesn't like it)
        if ((!empty($event->getEventId()) && is_string($event->getEventId())) || (is_int($event->getEventId()) && $event->getEventId() > 0)) {
            $vEvent->add('UID', $event->getEventId() . '/' . $event->getConnectorId());
        }
        $vEvent->add('CATEGORIES', $this->getCategories());
        $vEvent->add('LOCATION', $event->getLocation());

        $this->getTextConverter()->setHtml($event->getDetail());
        $vEvent->add('DESCRIPTION', $this->getTextConverter()->getText());

        $start_time = $event->getStartTime()->getDateTimeObject();
        if (!($start_time instanceof DateTime)) {
            $start_time = new DateTime($event->getStartTime()->getDatabase());
        }
        $end_time = $event->getEndTime()->getDateTimeObject();
        if (!($end_time instanceof DateTime)) {
            $end_time = new DateTime($event->getEndTime()->getDatabase());
        }
        if (!$event->getAllday()) {
            $start_time->setTimezone($utcTimezone);
            $end_time->setTimezone($utcTimezone);
            $vEvent->add('DTSTART', $start_time);
            $vEvent->add('DTEND', $end_time);
        } else {
            // the end date for multi-day event should be non-inclusive date. (rfc5545#page-54)
            // adding one day for one-day event as well for outlook.
            $end_time->add(new DateInterval('P1D'));
            $vEvent->add('DTSTART', $start_time->format('Ymd'), ['VALUE' => 'DATE']);
            $vEvent->add('DTEND', $end_time->format('Ymd'), ['VALUE' => 'DATE']);
        }

        $vEvent->add('SUMMARY', $event->getTitle());

        return $vEvent->serialize();
    }


    protected function getCategories(): string
    {
        /** @var EventInterface $event */
        $event = $this->getEvent();
        return $event->getEventType();
    }


    /**
     * @return VCalendar
     */
    protected function getVCalendar(): VCalendar
    {
        if (!$this->vCalendar) {
            $this->vCalendar = new VCalendar();
        }
        return $this->vCalendar;
    }


    /**
     * @return Html\ToText
     */
    protected function getTextConverter(): Html\ToText
    {
        if (!$this->textConverter) {
            $this->textConverter = new Html\ToText();
        }
        return $this->textConverter;
    }
}
