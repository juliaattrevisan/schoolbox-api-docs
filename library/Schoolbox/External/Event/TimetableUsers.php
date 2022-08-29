<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event;

use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\User;
use Schoolbox\UserMapper;

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
     * INVOLVEMENT_FUTURE_THRESHOLD is the number of weeks into the future to look
     * for involvements. You might not be a teacher of a class today,
     * but you might be some time later.
     *
     * INVOLVEMENT_PAST_THRESHOLD is the number of weeks into the past to look
     * for involvements. You might have been the teacher of a class
     * at some point in the past, but no longer
     *
     * INVOLVEMENT_PAST_THRESHOLD and INVOLVEMENT_FUTURE_THRESHOLD are both
     * defaulted to 1 week just because I said so. This gives you a span of
     * 2 weeks where your involvements are examined.
     *
     * We don't know your involvements outside of this interval.
     */
    public const INVOLVEMENT_PAST_THRESHOLD = -1;
    public const INVOLVEMENT_FUTURE_THRESHOLD = 1;


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
     * @var \Schoolbox\External\Profile\Staff\Mapper
     */
    private \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper;


    /**
     * Remember this set if we fetched it already
     *
     * @var array|null
     */
    private ?array $staffExternalIds = null;


    /**
     * @param ClassEntryMapper                           $classEntryMapper
     * @param ClassInvolvementMapper                     $classInvolvementMapper
     * @param \Schoolbox\External\Event\Mapper           $eventMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overrideMapper
     * @param UserMapper                                 $userMapper
     * @param \Schoolbox\External\Profile\Staff\Mapper   $staffProfileMapper
     */
    public function __construct(
        \Schoolbox\External\ClassEntryMapper $classEntryMapper,
        \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper,
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overrideMapper,
        \Schoolbox\UserMapper $userMapper,
        \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper
    ) {
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->eventMapper = $eventMapper;
        $this->overrideMapper = $overrideMapper;
        $this->userMapper = $userMapper;
        $this->staffProfileMapper = $staffProfileMapper;
    }


    /**
     * Finds all staff externalIds involved in $classCode between the two timestamps,
     * taking into account override mutations
     *
     * @param string $classCode
     * @param int    $startTimestamp
     * @param int    $endTimestamp
     * @return array
     * @throws \PDOException
     */
    public function findTeachersForClassCode(string $classCode, int $startTimestamp, int $endTimestamp): array
    {
        /* Micromanage a stack of minimal event data with overrides
         */
        $stack = [];

        /* Find the usual guys for the given class code out of sis_class_user
         */
        $query = "
            SELECT DISTINCT se.code, scu.*
            FROM sis_class_user scu
            INNER JOIN sis_class sc ON scu.class_id = sc.id
            INNER JOIN sis_event se ON sc.code = se.code
            WHERE sc.code = :classCode
            AND se.classification = :classification
            ORDER BY sc.id ASC
        ";

        $statement = $this->eventMapper->getDatabase()->prepare($query);
        $statement->execute([
            'classCode' => $classCode,
            'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
        ]);

        /* Pile it into the $stack in preparation for the mutations
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($stack[$row['code']])) {
                $stack[$row['code']] = [
                    'code' => $row['code'],
                    'usuals' => [],
                    /* A list of people who survived all the mutations
                     * and transformations in this algorithm
                     */
                    'manifest' => [],
                ];
            }

            /* Insert and dedupe
             */
            $stack[$row['code']]['usuals'][$row['external_id']] = $row['external_id'];
        }

        /* Get a list of events between $startTimestamp and $endTimestamp
         * along with an ordered list of their overrides
         */
        $query = "
            SELECT se.id, se.code, seuo.*
            FROM sis_event se
            INNER JOIN sis_event_user_overrides seuo ON se.id = seuo.event_id
            WHERE se.code = :classCode
            AND se.classification = :classification
            AND se.start_at >= :from
            AND se.start_at <= :to
            ORDER BY se.id ASC, seuo.sequence ASC
        ";

        $statement = $this->eventMapper->getDatabase()->prepare($query);
        $statement->execute([
            'classCode' => $classCode,
            'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
            'from' => \Alaress_Date_Format::newFromTimestamp($startTimestamp)->getDatabase(),
            'to' => \Alaress_Date_Format::newFromTimestamp($endTimestamp)->getDatabase(),
        ]);

        /* Remember: overrides are generally optional so they might not even
         * exist for $classCode which is why we get the Usual Guys first
         *
         * Assumption:
         *   We can presume that sis_class_user data exists first simply because
         *   that's how we chose to build things. To have overrides only, and
         *   nothing for sis_class and sis_class_user, is a bit weird and is
         *   probably a broken situation. I thought of making a manifest for
         *   each sis_event, but the data size would be extremely big so we chose
         *   not to do that and opted for this hybrid approach instead. In the
         *   grand scheme of things it would have been easier to have that
         *   manifest instead, but I was overridden
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $stack[$row['code']]['overrides'][] = [
                'external_id' => $row['external_id'],
                'action' => $row['action']
            ];
        }

        /* Perform mutations
         */
        $manifest = $this->applyOverrides($stack[$classCode]['usuals'], $stack[$classCode]['overrides']);

        /* Since we're explicitly looking for staff members in this method
         * we can basically kick out everyone else who isn't, if they
         * survived the override mutations
         *
         * If we haven't got this already, get it and remember it
         * in case we needed it again and again and again
         */
        if (null === $this->staffExternalIds) {
            $this->staffExternalIds = $this->staffProfileMapper->findAllExternalIds();
        }

        return array_values(array_intersect($this->staffExternalIds, $manifest));
    }


    /**
     * Generate a list of events in which $user is involved, between two timestamps
     * Also takes into account any event overrides
     *
     * @param User                   $user
     * @param int                    $startTimestamp
     * @param int                    $endTimestamp
     * @param GenericCollection|null $folderCodes
     * @return array
     * @throws \PDOException
     */
    public function findClassCodesForUser(\Schoolbox\User $user, int $startTimestamp, int $endTimestamp, ?GenericCollection $folderCodes = null): array
    {
        /* Micromanage a stack of minimal event data with overrides
         */
        $lookup = [];
        $stack = [];

        /* Micromanage dates
         */
        $from = \Alaress_Date_Format::newFromTimestamp($startTimestamp)->getDatabase();
        $to = \Alaress_Date_Format::newFromTimestamp($endTimestamp)->getDatabase();

        /* Find the usual guys for the given class code out of sis_class_user
         * In this case, $user will be the only guy in the list since we don't
         * actually care about anyone else. We only care when $user is one of
         * a class' usual guys.
         */
        $query = "
            SELECT se.code, sc.name, scu.*
            FROM sis_class_user scu
            INNER JOIN sis_class sc ON scu.class_id = sc.id
            INNER JOIN sis_event se ON sc.code = se.code
            WHERE scu.external_id = :externalId
            AND se.classification = :classification
            AND se.start_at >= :from
            AND se.start_at <= :to
            ORDER BY sc.id ASC
        ";

        $statement = $this->eventMapper->getDatabase()->prepare($query);
        $statement->execute([
            'externalId' => $user->getExternalId(),
            'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
            'from' => $from,
            'to' => $to,
        ]);

        /* Pile it into the $lookup in preparation for the mutations across multiple
         * instances of a sis_event. A class code could have different sets of humans
         * involved on different days so you have to repeat the process for every
         * sis_event you find
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($lookup[$row['code']])) {
                $lookup[$row['code']] = [
                    'name' => $row['name'],
                    'code' => $row['code'],
                    /* The guys who are usually involved in this class
                     */
                    'usuals' => [],
                    /* A list of overrides index by event id since each event
                     * has its own list of overrides
                     */
                    'overrides' => [],
                    /* A list of people who survived all the mutations
                     * and transformations in this algorithm, for each event instance
                     */
                    'manifest' => [],
                ];
            }

            /* Insert and dedupe
             */
            $lookup[$row['code']]['usuals'][$row['external_id']] = $row['external_id'];
        }

        /* Find all the events within the time interval specified, because $user
         * could have been affected by any of their overrides - you won't know
         * until you play through all the mutations
         */
        $query = "
            SELECT se.code, se.title, se.id, seuo.*
            FROM sis_event se
            INNER JOIN sis_event_user_overrides seuo ON se.id = seuo.event_id
            WHERE se.classification = :classification
            AND se.start_at >= :from
            AND se.start_at <= :to
            ORDER BY se.id, seuo.sequence
        ";

        $statement = $this->eventMapper->getDatabase()->prepare($query);
        $statement->execute([
            'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
            'from' => $from,
            'to' => $to,
        ]);

        /* Collate all the overrides and group them so we can mutate everything
         * in a convenient manner
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($lookup[$row['code']])) {
                $lookup[$row['code']] = [
                    'name' => $row['title'],
                    'code' => $row['code'],
                    /* The guys who are usually involved in this class
                     */
                    'usuals' => [],
                    /* A list of overrides index by event id since each event
                     * has its own list of overrides
                     */
                    'overrides' => [],
                    /* A list of people who survived all the mutations
                     * and transformations in this algorithm, for each event instance
                     */
                    'manifest' => [],
                ];
            }

            /* Manufacture a list of overrides for each event id
             */
            $lookup[$row['code']]['overrides'][$row['id']][] = [
                'external_id' => $row['external_id'],
                'action' => $row['action']
            ];
        }

        /* For every thing we found in $lookup,
         * Play back all the overrides against $usuals
         * then collate the list of surviving guys in $manifest
         */
        foreach ($lookup as $classCode => &$data) {
            foreach ($data['overrides'] as $eventId => $overrides) {
                $data['manifest'][$eventId] = $this->applyOverrides($data['usuals'], $overrides);

                /* If you were involved in this class at any point within the
                 * time interval specified, I suppose you can pile its class code
                 * into the stack and skip processing other overrides - we don't
                 * need to investigate further, since we know you're involved at
                 * least once.
                 */
                if (isset($data['manifest'][$eventId][$user->getExternalId()])) {
                    $stack[$data['code']] = [
                        'name' => $data['name'],
                        'code' => $data['code'],
                    ];

                    break;
                }
            }
        }

        unset($data);

        return array_values($stack);
    }


    /**
     * Apply $overrides to a set of usual guys $usuals,
     * to see who still remains. Everyone still surviving
     * is the event's $manifest
     *
     * @param array $usuals
     * @param array $overrides
     * @return array
     * @throws \PDOException
     */
    private function applyOverrides(array $usuals, array $overrides): array
    {
        $manifest = $usuals;

        foreach ($overrides as $override) {
            if (\Schoolbox\External\Event\Overrides\Model::SUBTRACT === $override['action']) {
                unset($manifest[$override['external_id']]);
            } elseif (\Schoolbox\External\Event\Overrides\Model::ADD === $override['action']) {
                $manifest[$override['external_id']] = $override['external_id'];
            } elseif (\Schoolbox\External\Event\Overrides\Model::CLEAR === $override['action']) {
                $manifest = [];
            } elseif (\Schoolbox\External\Event\Overrides\Model::CLEAR_STAFF === $override['action']) {
                /* If we haven't got this already, get it and remember it
                 * in case we needed it again and again and again
                 */
                if (null === $this->staffExternalIds) {
                    $this->staffExternalIds = $this->staffProfileMapper->findAllExternalIds();
                }

                /* Get a list of staff members,
                 * then do some array magic between $usualUsers
                 * and this list of staff ids, to exclude staff
                 * from $usualUsers
                 */
                $manifest = array_diff($manifest, $this->staffExternalIds);
            }
        }

        return $manifest;
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
     * Find the overall set of involved humans for $event
     *
     * @param Model $event
     * @return array
     * @throws \PDOException
     * @throws Exception
     * @throws Deleted
     */
    private function findForEvent(\Schoolbox\External\Event\Model $event): array
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
                /* If we haven't got this already, get it and remember it
                 * in case we needed it again and again and again
                 */
                if (null === $this->staffExternalIds) {
                    $this->staffExternalIds = $this->staffProfileMapper->findAllExternalIds();
                }

                /* Get a list of staff members,
                 * then do some array magic between $usualUsers
                 * and this list of staff ids, to exclude staff
                 * from $usualUsers
                 */
                $usualUsers = array_diff($usualUsers, $this->staffExternalIds);
            }
        }

        return $usualUsers;
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
            AND seuo.external_id = :externalId
        ";

        $params = [
            'externalId' => $user->getExternalId(),
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
