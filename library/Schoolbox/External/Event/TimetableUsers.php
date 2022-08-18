<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event;

use Schoolbox\GenericCollection;

/**
 * For a given event, find the final calculated list of users
 * which is the default users from sis_class, mutated by the
 * Overrides, then deduped.
 *
 * Only applies to the 'timetable' event classification
 */
class TimetableUsers
{

    /**
     * @var \Schoolbox\External\ClassEntryMapper
     */
    private \Schoolbox\External\ClassEntryMapper $classEntryMapper;


    /**
     * @var \Schoolbox\External\ClassInvolvementMapper
     */
    private \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper;


    /**
     * @var \Schoolbox\External\Event\Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


    /**
     * @var \Schoolbox\External\Event\Overrides\Mapper
     */
    private \Schoolbox\External\Event\Overrides\Mapper $overrideMapper;


    /**
     * @var \Schoolbox\UserMapper
     */
    private \Schoolbox\UserMapper $userMapper;


    /**
     * @param \Schoolbox\External\ClassEntryMapper       $classEntryMapper
     * @param \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper
     * @param \Schoolbox\External\Event\Mapper           $eventMapper
     * @param Overrides\Mapper                           $overrideMapper
     * @param \Schoolbox\UserMapper                      $userMapper
     */
    public function __construct(
        \Schoolbox\External\ClassEntryMapper $classEntryMapper,
        \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper,
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overrideMapper,
        \Schoolbox\UserMapper $userMapper
    ) {
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->eventMapper = $eventMapper;
        $this->overrideMapper = $overrideMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * Find the overall set of involved humans for $event
     *
     * @param \Schoolbox\External\Event\Model $event
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Schoolbox\Mapper\Exception\NotFound
     */
    public function findForEvent(\Schoolbox\External\Event\Model $event): array
    {

        /* Find the sis_class that corresponds to $event
         */
        $class = $this->classEntryMapper->findOneByColumn('code', $event->getCode());

        /* Find the usual guys in class 11
         */
        $involvements = $this->classInvolvementMapper->findByColumn('class_id', $class->getId());

        $usualUsers = [];

        foreach ($involvements as $involvement) {
            $usualUsers[$involvement->getExternalId()] = $involvement->getExternalId();
        }

        /* Find the overrides
         */
        $overrides = $this->overrideMapper->findForEvent($event->getId());

        /* Decide how each $override affects the $stack set
         */
        /** @var \Schoolbox\External\Event\Overrides\Model $override */
        foreach ($overrides as $override) {
            if (\Schoolbox\External\Event\Overrides\Model::SUBTRACT === $override->getAction()) {
                unset($usualUsers[$override->getUser()->getExternalId()]);
            } elseif (\Schoolbox\External\Event\Overrides\Model::ADD === $override->getAction()) {
                $usualUsers[$override->getUser()->getExternalId()] = $override->getUser()->getExternalId();
            } elseif (\Schoolbox\External\Event\Overrides\Model::CLEAR === $override->getAction()) {
                $usualUsers = [];
            } elseif (\Schoolbox\External\Event\Overrides\Model::CLEAR_STAFF === $override->getAction()) {
                /* Get a list of staff members
                 */

                /* Index them by their externalId (synergy_id)
                 */

                /* Do some array magic between $usualUsers
                 * and this list of staff ids, to exclude staff
                 * from $usualUsers
                 */
            }
        }

        return $usualUsers;
    }


    /**
     * Generate a list of events in which $user is involved, between two timestamps
     * Also takes into account any event overrides
     *
     * @param \Schoolbox\User        $user
     * @param int                    $startTimestamp
     * @param int                    $endTimestamp
     * @param GenericCollection|null $folderCodes
     * @return GenericCollection
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Schoolbox\Mapper\Exception\NotFound
     * @throws \UnderflowException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function findForUser(\Schoolbox\User $user, int $startTimestamp, int $endTimestamp, ?GenericCollection $folderCodes = null): GenericCollection
    {
        $startDatetime = \Alaress_Date_Format::newFromTimestamp($startTimestamp)->getDatabase();
        $finishDatetime = \Alaress_Date_Format::newFromTimestamp($endTimestamp)->getDatabase();

        /* This is the default scenario for the most part; it's all the events
         * that $user is usually involved with via sis_class_user
         */
        $usualEventIds = $this->generateUsualEventIds($user, $startDatetime, $finishDatetime, $folderCodes);

        $events = $this->eventMapper->findWithCheapStaff($usualEventIds)->indexBy('id');

        /* Find a list of events which have overrides that might affect $user
         * If there are overrides, process each one specially
         */
        $overrideEventIds = $this->generateOverrideEventIds($user, $startDatetime, $finishDatetime, $folderCodes);

        /* No overrides? No trabajo
         */
        if (!\count($overrideEventIds)) {
            return $events;
        }

        /* Figure out the mutations that were specified by the event overrides
         * and then decide if $user was still involved
         */
        $overrideEvents = $this->eventMapper->findByColumn('id', $overrideEventIds);

        foreach ($overrideEvents as $overrideEvent) {
            $mutatedInvolvements = $this->findForEvent($overrideEvent);

            /* If you're still in the list after the mutations; pile it into the stack
             * Otherwise, you aren't involved anymore; remove it from the stack
             */
            if (isset($mutatedInvolvements[$user->getExternalId()])) {
                $events[$overrideEvent->getId()] = $overrideEvent;
            } else {
                unset($events[$overrideEvent->getId()]);
            }

            /* These are the staff members now involved in $event (they're the teachers)
             * If this event hasn't been kicked out due to your lack of involvement, go
             * find all the staff members who are now in it due to mutations
             *
             * TODO Perhaps find a less expensive way to do this
             */
            if (isset($events[$overrideEvent->getId()])) {
                $events[$overrideEvent->getId()]->setStaff($this->findMutatedStaffInvolvements($mutatedInvolvements));
            }
        }

        return $events;
    }


    /**
     * Get a list of events between $startDatetime and $finishDatetime
     * where $user is in the usual involvement list specified by sis_class_user
     *
     * Optionally filters to $folderCode
     *
     * @param \Schoolbox\User        $user
     * @param string                 $startDatetime
     * @param string                 $finishDatetime
     * @param GenericCollection|null $folderCodes
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnderflowException
     */
    private function generateUsualEventIds(\Schoolbox\User $user, string $startDatetime, string $finishDatetime, ?GenericCollection $folderCodes = null): array
    {
        /* Find the list of events where you might be involved normally
         */
        $query = "
            SELECT se.id
            FROM {$this->eventMapper->getTable()} se
            INNER JOIN {$this->classEntryMapper->getTable()} sc ON se.code = sc.code
            INNER JOIN {$this->classInvolvementMapper->getTable()} scu ON scu.class_id = sc.id
            INNER JOIN {$this->userMapper->getTable()} u ON u.synergy_id = scu.external_id
            INNER JOIN {$this->userMapper->getJoinSql('role', 'u', 'g')}
            LEFT JOIN {$this->overrideMapper->getTable()} seuo ON se.id = seuo.event_id
            WHERE se.classification = :classification
            AND se.start_at >= :startDatetime
            AND se.finish_at <= :finishDatetime
            AND u.synergy_id = :externalId
            AND u.deleted_at IS NULL
        ";

        $params = [
            'startDatetime' => $startDatetime,
            'finishDatetime' => $finishDatetime,
            'externalId' => $user->getExternalId(),
            'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE
        ];

        if (null === $folderCodes) {
            /* $folderCodes was never set; you're looking for data via the main calendar
             * No filtering required
             */
        } elseif ($folderCodes->count()) {
            /* $folderCodes was set, but now we have to check the counts
             * Folders which do have a code should be filtered to that code specifically
             */
            $pb = $this->eventMapper->getPredicateBuilder();
            $pb->in('se.code', $folderCodes->keys());

            $where = $pb->getWhere()[0];
            $params = array_merge($params, $pb->getParams());

            $query .= " AND {$where}";
        } else {
            /* $folderCodes was set, but now we have to check the counts
             * Folders which don't have a code should display nothing from this subscription
             */
            return [];
        }

        $statement = $this->overrideMapper->getDatabase()->prepare($query);
        $statement->execute($params);

        $usualEventIds = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $usualEventIds[$row['id']] = $row['id'];
        }

        return $usualEventIds;
    }


    /**
     * Get a list of events between $startDatetime and $finishDatetime
     * where each event has some kind of override that can affect $user's
     * usual involvement via sis_class_user, or the involvement with other
     * people (such as multiple staff teaching this class where one staff
     * is specified via an override)
     *
     * Optionally filters to $folderCode
     *
     * @param string          $startDatetime
     * @param string          $finishDatetime
     * @param GenericCollection|null $folderCodes
     * @param \Schoolbox\User        $user
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \UnderflowException
     */
    private function generateOverrideEventIds(\Schoolbox\User $user, string $startDatetime, string $finishDatetime, ?GenericCollection $folderCodes = null): array
    {
        $query = "
            SELECT se.id
            FROM {$this->eventMapper->getTable()} se
            INNER JOIN {$this->overrideMapper->getTable()} seuo ON se.id = seuo.event_id
            WHERE se.classification = :classification
            AND se.start_at >= :startDatetime
            AND se.finish_at <= :finishDatetime
        ";

        $params = [
            'startDatetime' => $startDatetime,
            'finishDatetime' => $finishDatetime,
            'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
        ];

        if (null === $folderCodes) {
            /* $folderCodes was never set; you're looking for data via the main calendar
             * No filtering required
             */
        } elseif ($folderCodes->count()) {
            /* $folderCodes was set, but now we have to check the counts
             * Folders which do have a code should be filtered to that code specifically
             */
            $pb = $this->eventMapper->getPredicateBuilder();
            $pb->in('se.code', $folderCodes->keys());

            $where = $pb->getWhere()[0];
            $params = array_merge($params, $pb->getParams());

            $query .= " AND {$where}";
        } else {
            /* $folderCodes was set, but now we have to check the counts
             * Folders which don't have a code should display nothing from this subscription
             */
            return [];
        }

        $statement = $this->overrideMapper->getDatabase()->prepare($query);
        $statement->execute($params);

        $overrideEventIds = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $overrideEventIds[$row['id']] = $row['id'];
        }

        return $overrideEventIds;
    }


    /**
     * Find all the staff members in the externalId list $externalIds,
     * which is a result of override mutation
     *
     * Returns a stack of (user id, name) tuples, e.g.
     * (22, Mr Jack Coles), (24, Ms Rebecca White)
     *
     * @param array $externalIds
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \UnderflowException
     */
    private function findMutatedStaffInvolvements(array $externalIds): array
    {
        $pb = $this->userMapper->getPredicateBuilder();
        $pb->in('u.synergy_id', $externalIds);

        $where = $pb->getWhere()[0];

        $params = array_merge([
            'groupType' => \Schoolbox\Role::STAFF
        ], $pb->getParams());

        $query = "
            SELECT DISTINCT u.id, u.synergy_id, u.title, u.firstname, u.lastname
            FROM {$this->userMapper->getTable()} u
            INNER JOIN `groups` g on u.group_id = g.id
            WHERE g.group_type = :groupType
            AND g.deleted_at IS NULL
            AND u.deleted_at IS NULL
            AND {$where}
        ";

        $statement = $this->userMapper->getDatabase()->prepare($query);
        $statement->execute($params);

        $stack = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $stack[$row['id']] = implode(' ', array_filter([
                $row['title'],
                $row['firstname'],
                $row['lastname']
            ], static function ($val) {
                return '' !== trim((string)$val);
            }));
        }

        return $stack;
    }
}
