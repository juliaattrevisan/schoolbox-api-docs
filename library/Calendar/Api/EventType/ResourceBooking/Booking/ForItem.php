<?php

/**
 * Event source - represents all bookings for a single item.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Calendar\Event\Collection;
use Schoolbox\User;

class Calendar_Api_EventType_ResourceBooking_Booking_ForItem extends Calendar_Api_EventType_ResourceBooking_Base
{

    /**
     * The IDs of the resource to fetch. Does not filter by resourceId if NULL.
     *
     * @var null|array
     */
    private $resourceIds;


    /**
     * @param int        $connectorId
     * @param User       $user
     * @param User       $viewer
     * @param null|int[] $resourceIds
     * @throws DomainException If resource booking module is disabled in config
     */
    public function __construct(int $connectorId, User $user, User $viewer, array $resourceIds = null)
    {
        parent::__construct($connectorId, $user, $viewer);
        $this->resourceIds = $resourceIds;
    }


    /**
     * Get day events from this data source
     *
     * @param int $startTimestamp
     * @param int $endTimestamp
     *
     * @return Collection
     */
    public function getEvents($startTimestamp, $endTimestamp)
    {
        // Get all items out of booking mapper for this resource.
        $bookings = $this->getBookingMapper()->findForResource(
            $this->resourceIds,
            $startTimestamp,
            $endTimestamp,
            $this->getStatus()
        );
        $collection = new Collection();
        foreach ($bookings as $booking) {
            $calendarEvent = $this->getEventConverter()->convertEventToCalendarEvent(
                $booking->getEvent(),
                $booking
            );
            $calendarEvent->setConnectorId($this->getConnectorId());
            $collection->add($calendarEvent);
        }
        return $collection;
    }
}
