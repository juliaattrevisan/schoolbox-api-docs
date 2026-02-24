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
     * In the resource booking section, we are mostly looking at bookings of a particular resource, and the
     * name of that resource is in the page heading.  For example, on the resource booking calendar the page
     * name is the resource name.  So we don't need to repeat the resource name on every calendar item that
     * represents a booking of that resource.
     *
     * Instead we show event.title (for events that are visible on the main calendar), or resource_booking.name.
     * Only if the preferred one of those is blank or null do we fall back on the default title.
     *
     * Notes:
     *  * Events of variant CalendarEvent::VARIANT_EVENT are the ones shown on the main calendar.  Events of
     *    variant CalendarEvent::VARIANT_BOOKING do not appear on the main calendar.
     *  * The contents of resource_booking.name will depend on the section in which the booking was created:
     *     * For bookings created in the Resource Booking section, resource_booking.name will have been set to the
     *       contents of the 'Purpose' field on the 'create booking' form.
     *     * For bookings created in the Main Calendar section, resource_booking.name will have been set to the name
     *       of the booked resource.
     */
    public function getTitleForResourceBooking(): string
    {
        $bookingName = (
            $this->getEventVariant() === CalendarEvent::VARIANT_EVENT
            ? $this->getOriginalEvent()?->getTitle()
            : $this->getBooking()->getName()
        );

        return (
            is_string($bookingName) && trim($bookingName) !== ''
            ? $bookingName
            : $this->getTitle()
        );
    }

    /**
     * @inheritDoc
     */
    public function getTitleForExport()
    {
        return $this->getTitle() . ($this->getBooking()->getStatus()->isPending() ? ' (Pending)' : '');
    }

    /**
     * Since we have a special function for getting the title for resource booking events,
     * we also need a special function for exporting the title for resource booking events.
     */
    public function getTitleForResourceBookingExport(): string
    {
        return $this->getTitleForResourceBooking() . ($this->getBooking()->getStatus()->isPending() ? ' (Pending)' : '');
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
