<?php

use Calendar\Event\Type\EventInterface;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Like Calendar_Api_EventType_Event_Schoolbox_Default, but only returns events
 * for a given group.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class Calendar_Api_EventType_Event_Schoolbox_Group extends Calendar_Api_EventType_Event_Schoolbox_Default
{
    /**
     * The group to get events for. Does not filter by group if NULL
     *
     * @var null|int
     */
    private $group_id;


    /**
     * @param int      $connectorId
     * @param User     $user
     * @param User     $viewer
     * @param int|null $groupId
     */
    public function __construct(int $connectorId, User $user, User $viewer, ?int $groupId = null)
    {
        parent::__construct($connectorId, $user, $viewer);
        $this->group_id = $groupId;
    }


    /**
     * Overridden to get events only for this group.
     *
     * @param int        $startTimestamp
     * @param int        $endTimestamp
     * @param array|null $allowedEventTypes
     * @return GenericCollection<EventInterface>
     */
    protected function getEventsFromCalendarHandler(
        int $startTimestamp,
        int $endTimestamp,
        ?array $allowedEventTypes = null,
    ): GenericCollection {
        /* Manufacture a special $filters array
        /* Do a detection on $allowedEventTypes, if provided and meaningful
         */
        $filter = [
            'group' => $this->group_id
        ];

        if (null !== $allowedEventTypes && \count($allowedEventTypes)) {
            $filter['typeSet'] = $allowedEventTypes;
        }

        return $this->getCalendarHandler()->getDayEvents(
            $this->getUser(),
            $this->getViewer(),
            $startTimestamp,
            $endTimestamp,
            $filter
        );
    }
}
