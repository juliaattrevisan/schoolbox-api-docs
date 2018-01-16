<?php
/**
 * Event Source for bookings that this user moderates.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Authorizer\Rule;
use Alaress\Authorizer\Failure;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\ResourceBooking\Booking;
use Calendar\Event\Collection;

class Calendar_Api_EventType_ResourceBooking_Moderation_Default extends Calendar_Api_EventType_ResourceBooking_Base
{

    /**
     * Can the current user moderate ANY resource item?
     *
     * @var bool
     */
    protected $userCanModerate = false;

    /**
     * If this user is restricted to moderating certain items, then the item IDs
     * are stored here.
     *
     * $this->userCanModerate == true && empty($this->userRestrictedToIds)
     * means that user can moderate any item.
     *
     * @var array
     */
    protected $userRestrictedToIds = array();

    /**
     * Sets up permissions for moderated items
     */
    protected function setup()
    {
        parent::setup();
        $this->loadPermissions();
    }

    /**
     * Loads permissions for resource bookings - whether the user can moderate
     * at all, and the items they're restricted to if any.
     *
     * @return Collection
     */
    protected function loadPermissions()
    {
        // We no longer show moderators bookings for all items [SB-6802]
        // Maybe they are a moderator for some items?
        $items = $this->getBookingMapper()->getJoinMapper('resource')->findForModerator(
            $this->getUser()->getId()
        );
        foreach ($items as $item) {
            $restrictedIds[] = $item->getId();
        }
        // If I still don't moderate any items - return empty collection.
        // There should be notice that this user doesn't moderate any items.
        if (empty($restrictedIds)) {
            $this->setUserCanModerate(false);
        } else {
            $this->setUserCanModerate(true)->setUserRestrictedToIds($restrictedIds);
        }
    }

    /**
     * Get day events from this data source
     *
     * @param int $startTimestamp
     * @param int $endTimestamp
     *
     * @return Collection|Calendar_Event_Type_ResourceBooking[]
     */
    public function getEvents($startTimestamp, $endTimestamp)
    {

        // If I can't moderate anything - don't even bother looking.
        if (!$this->getUserCanModerate()) {
            return new Collection();
        }

        // Get bookings and convert them into a collection of calendar events.
        $params        = array();
        $restrictedIds = $this->getUserRestrictedToIds();
        if (!empty($restrictedIds)) {
            $params['resource_id'] = $restrictedIds;
        }
        $bookings = $this->getBookingMapper()->findForTimespan(
            $startTimestamp,
            $endTimestamp,
            $this->getStatus(),
            $params
        );
        $converter  = new Booking\Util\EventConverter();
        $collection = new Collection();
        foreach ($bookings as $booking) {
            // Don't add this booking if it's my own, it will show in my
            // resource booking event feed.
            if ($booking->getEvent()->getOwner()->getId() == $this->getUser()->getId()) {
                continue;
            }
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
     * @return Collection
     */
    public function getEvent($eventId)
    {
//        return $this->getCalendarHandler()->getEvent($eventId);
    }

    /**
     * Set by $this->loadPermissions(), therefore shouldn't be set outside this
     * class.
     *
     * @param  bool  $userCanModerate
     * @return $this
     */
    protected function setUserCanModerate($userCanModerate)
    {
        $this->userCanModerate = $userCanModerate;
        return $this;
    }

    /**
     * @return bool
     */
    public function getUserCanModerate()
    {
        return $this->userCanModerate;
    }

    /**
     * Set by $this->loadPermissions(), therefore shouldn't be set outside this
     * class.
     *
     * @param  array $userRestrictedToIds
     * @return $this
     */
    protected function setUserRestrictedToIds($userRestrictedToIds)
    {
        $this->userRestrictedToIds = $userRestrictedToIds;
        return $this;
    }

    /**
     * @return array
     */
    public function getUserRestrictedToIds()
    {
        return $this->userRestrictedToIds;
    }

    /**
     * @param  Booking\Mapper $bookingMapper
     * @return $this
     */
    public function setBookingMapper($bookingMapper)
    {
        $this->bookingMapper = $bookingMapper;
        return $this;
    }

    /**
     * @return Booking\Mapper
     */
    public function getBookingMapper()
    {
        return $this->bookingMapper;
    }
}
