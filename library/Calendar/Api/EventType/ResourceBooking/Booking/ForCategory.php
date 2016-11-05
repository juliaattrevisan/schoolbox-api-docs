<?php
/**
 * Event source - represents all bookings for a single category.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\ResourceBooking\Booking;
use Calendar\Event\Collection;

class Calendar_Api_EventType_ResourceBooking_Booking_ForCategory extends Calendar_Api_EventType_ResourceBooking_Base {

    /**
     * The ID of the resource category to fetch.
     *
     * @var int
     */
    protected $categoryId;

    /**
     * Get day events from this data source
     *
     * @param int $startTimestamp
     * @param int $endTimestamp
     *
     * @return Collection
     */
    public function getEvents($startTimestamp, $endTimestamp) {
        // Get all items out of booking mapper for this resource.
        $bookings = $this->getBookingMapper()->findForCategory(
            $this->getCategoryId(),
            $startTimestamp,
            $endTimestamp,
            $this->getStatus()
        );
        $collection = new Collection();
        foreach ($bookings as $booking) {
            $calendarEvent = $this->getEventConverter()->convertEventToCalendarEvent(
                $booking->getEvent(), $booking
            );
            $calendarEvent->setConnectorId($this->getConnectorId());
            $collection->add($calendarEvent);
        }
        return $collection;
    }

    /**
     * @param  int|array $categoryId
     * @return $this
     */
    public function setCategoryId($categoryId) {
        $this->categoryId = $categoryId;
        return $this;
    }

    /**
     * @return int
     */
    public function getCategoryId() {
        return $this->categoryId;
    }

}
