<?php
/**
 * Event type for bookings that this user moderates.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Authorizer\Rule;
use Alaress\Authorizer\Failure;
use Schoolbox\UserAccess;
use Schoolbox\ResourceBooking\Booking;

class Calendar_Api_EventType_ResourceBooking_Moderation_Default extends Calendar_Api_EventType_Abstract {

    /**
     * Constructor
     *
     * @param $user
     * @param $config
     */
    public function __construct($user, $config) {
        $this->setUser($user);
    }

    /**
     * Get day events from this data source
     *
     * @param int $startTimestamp
     * @param int $endTimestamp
     *
     * @return Calendar_Event_Collection
     */
    public function getEvents($startTimestamp, $endTimestamp) {

        $bookingMapper = Booking\Mapper::getInstance();

        // Permissions for bookings that this user migrates.
        $baseManageRule = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\HasServicePermission(
                UserAccess::SERVICE_RESOURCE_BOOKING,
                UserAccess::ACCESS_FULL
            )
        );
        $restrictedIds = array();
        try {
            $baseManageRule->authorize(array(), $this->getUser());
        }
        catch (Failure\Base $e) {
            // This user isn't a moderator.
            // Maybe they are a moderator for some items?
            $items = $bookingMapper->getJoinMapper('resource')->findForModerator(
                $this->user->getId()
            );
            foreach ($items as $item) {
                $restrictedIds[] = $item->getId();
            }
            // If I still don't moderate any items - return empty collection.
            // There should be notice that this user doesn't moderate any items.
            if (empty($restrictedIds)) {
                return new Calendar_Event_Collection();
            }
        }

        // Get bookings and convert them into a collection of calendar events.
        $bookings = $bookingMapper->findForTimespan(
            $startTimestamp,
            $endTimestamp,
            array(Booking\Status\Model::STATUS_PENDING),
            array('resource_id' => $restrictedIds,)
        );
        $converter  = new Booking\Util\EventConverter();
        $collection = new Calendar_Event_Collection();
        foreach ($bookings as $booking) {
            $calendarEvent = $converter->convertEventToCalendarEvent(
                $booking->getEvent(),
                $booking
            );
            $calendarEvent->setConnectorId($this->getConnectorId());
            $collection->add($calendarEvent);
        }
        return $collection;
    }

    /**
     * Find a particular event from this source
     *
     * @param $eventId
     * @return Calendar_Event_Collection
     */
    public function getEvent($eventId) {
//        return $this->getCalendarHandler()->getEvent($eventId);
    }
}
