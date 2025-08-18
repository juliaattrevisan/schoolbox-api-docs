<?php

/**
 * Event source - represents all bookings for a single category.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Calendar\Event\Type\EventInterface;
use Schoolbox\GenericCollection;
use Schoolbox\User;

class Calendar_Api_EventType_ResourceBooking_Booking_ForCategory extends Calendar_Api_EventType_ResourceBooking_Base
{
    /**
     * Resource category filter. Does not filter by resource category if NULL.
     *
     * @var null|int
     */
    private $categoryId;


    /**
     * @param int      $connectorId
     * @param User     $user
     * @param User     $viewer
     * @param int|null $categoryId A resource category id
     * @throws DomainException
     */
    public function __construct(int $connectorId, User $user, User $viewer, ?int $categoryId = null)
    {
        parent::__construct($connectorId, $user, $viewer);
        $this->categoryId = $categoryId;
    }


    /**
     * Get day events from this data source
     *
     * @param int        $startTimestamp
     * @param int        $endTimestamp
     * @param array|null $allowedEventTypes
     * @return GenericCollection<EventInterface>
     * @throws PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): GenericCollection
    {
        // Get all items out of booking mapper for this resource.
        $bookings = $this->getBookingMapper()->findForCategory(
            $this->categoryId,
            $startTimestamp,
            $endTimestamp,
            $this->getStatus(),
            $allowedEventTypes
        );
        $collection = new GenericCollection(EventInterface::class);
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
