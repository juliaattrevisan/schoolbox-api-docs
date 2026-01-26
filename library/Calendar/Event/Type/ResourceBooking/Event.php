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
        // If in the resource booking section, show whatever is stored in resource_booking.name.
        // Note the contents of resource_booking.name will depend on the section in which the booking was created:
        //  * For bookings created in the Resource Booking section, resource_booking.name will have been set to the
        //    contents of the 'Purpose' field on the 'create booking' form.
        //  * For bookings created in the Main Calendar section, resource_booking.name will have been set to the name
        //    of the booked resource.
        // (We are too deep here to inject the matched route (router->getMatcher()->getMatchedRoute()).
        //  To avoid changing many files, just infer the matched route from $_SERVER['REQUEST_URI'].)
        if (str_contains($_SERVER['REQUEST_URI'], '/resource-booking/')) {
            return trim($this->getBooking()->getName()) !== ''
                ? $this->getBooking()->getName()
                : $this->getBooking()->getResource()->getName(); // Fall back on the resource name if booking name blank.
        }

        // For all other sections (main calendar, etc.), show the resource name.
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
