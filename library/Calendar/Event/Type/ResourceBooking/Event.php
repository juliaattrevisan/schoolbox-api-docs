<?php

/**
 * Calendar event for resource bookings.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\ResourceBooking;

use Schoolbox\ResourceBooking\Booking\Model;
use Calendar\Event\Type\Day;

class Event extends Day\Event
{
    /**
     * @var Model
     */
    protected $booking;

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
     * @inheritDoc
     */
    public function getTitle()
    {
        return $this->getBooking()->getName() ?: $this->getBooking()->getResource()->getName();
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
        $location = $this->getBooking()->getResource()->getName();
        if ($this->getBooking()->getResource()->getRoomCode()) {
            $location .= ' [' . $this->getBooking()->getResource()->getRoomCode() . ']';
        }
        return $location;
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
