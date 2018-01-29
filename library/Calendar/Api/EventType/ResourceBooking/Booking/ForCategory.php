<?php
/**
 * Event source - represents all bookings for a single category.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Calendar\Event\Collection;
use Schoolbox\User;

class Calendar_Api_EventType_ResourceBooking_Booking_ForCategory extends Calendar_Api_EventType_ResourceBooking_Base {

    /**
     * Resource category filter. Does not filter by resource category if NULL.
     *
     * @var null|int
     */
    private $categoryId;


    /**
     * @param int      $connectorId
     * @param User     $user
     * @param null|int $categoryId   A resource category id
     * @throws DomainException
     */
    public function __construct(int $connectorId, User $user, int $categoryId = null)
    {
        parent::__construct($connectorId, $user);
        $this->categoryId = $categoryId;
    }


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
            $this->categoryId,
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
}
