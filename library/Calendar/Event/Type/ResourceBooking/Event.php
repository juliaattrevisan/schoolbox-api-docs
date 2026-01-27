<?php

/**
 * Calendar event for resource bookings.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\ResourceBooking;

use Schoolbox\Calendar\Event as CalendarEvent;
use Schoolbox\ResourceBooking\Booking\Model;
use Calendar\Event\Type\Day;

class Event extends Day\Event
{
    /**
     * @var Model
     */
    protected $booking;

    /**
     * @var null|CalendarEvent
     */
    protected ?CalendarEvent $originalEvent = null;

    /**
     * @param  Model $booking
     * @return $this
     */
    public function setBooking($booking)
    {
        $this->booking = $booking;
        return $this;
    }

    /**
     * @return Model
     */
    public function getBooking()
    {
        return $this->booking;
    }

    /**
     * @param  CalendarEvent $calendarEvent
     * @return $this
     */
    public function setOriginalEvent(CalendarEvent $calendarEvent)
    {
        $this->originalEvent = $calendarEvent;
        return $this;
    }

    /**
     * The original event, for when this event was created by ResourceBooking/Booking/Util/EventConverter.php.
     * @return null|CalendarEvent
     */
    public function getOriginalEvent(): ?CalendarEvent
    {
        return $this->originalEvent;
    }

    /**
     * @inheritDoc
     */
    public function getTitle()
    {
        return $this->getBooking()->getResource()->getName();
    }

    /**
     * @inheritDoc
     */
    public function getTitleForExport()
    {
        return $this->getTitle() . ($this->getBooking()->getStatus()->isPending() ? ' (Pending)' : '');
    }

    public function getLocation()
    {
        $location = $this->getBooking()->getEvent()->getLocation();
        if ($this->getBooking()->getResource()->getRoomCode()) {
            $location .= ' [' . $this->getBooking()->getResource()->getRoomCode() . ']';
        }
        return trim($location);
    }

    public function getCategory()
    {
        return $this->getBooking()->getResource()->getCategory()->getName();
    }


    /**
     * @return string[]
     */
    protected function getRenderClasses(): array
    {
        return [
            self::RENDER_FORMAT_JSON => Renderer\Javascript::class,
            self::RENDER_FORMAT_VCAL => Renderer\VObject::class,
            self::RENDER_FORMAT_CSV  => Renderer\Csv::class,
        ];
    }
}
