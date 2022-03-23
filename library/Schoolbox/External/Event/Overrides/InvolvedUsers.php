<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event\Overrides;

use Schoolbox\GenericCollection;

/**
 * For a given event, find the final calculated list of users
 * which is the default users from sis_class, mutated by the
 * Overrides, then deduped.
 *
 * Only applies to the 'timetable' event classification
 */
class InvolvedUsers
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
     * @var Mapper
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
     * @param Mapper                                     $overrideMapper
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
    public function generateForEvent(\Schoolbox\External\Event\Model $event): array
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
            if (Model::SUBTRACT === $override->getAction()) {
                unset($usualUsers[$override->getUser()->getExternalId()]);
            } elseif (Model::ADD === $override->getAction()) {
                $usualUsers[$override->getUser()->getExternalId()] = $override->getUser()->getExternalId();
            } elseif (Model::CLEAR === $override->getAction()) {
                $usualUsers = [];
            }
        }

        return $usualUsers;
    }


    /**
     * Generate a list of events in which $user is involved, between two timestamps
     * Also takes into account any event overrides
     *
     * @param \Schoolbox\User $user
     * @param int             $startTimestamp
     * @param int             $endTimestamp
     * @param string|null     $folderCode
     * @return GenericCollection
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Schoolbox\Mapper\Exception\NotFound
     */
    public function generateForUser(\Schoolbox\User $user, int $startTimestamp, int $endTimestamp, ?string $folderCode = null): GenericCollection
    {
        $startDatetime = \Alaress_Date_Format::newFromTimestamp($startTimestamp)->getDatabase();
        $finishDatetime = \Alaress_Date_Format::newFromTimestamp($endTimestamp)->getDatabase();

        /* This is the default scenario for the most part; it's all the events
         * that $user is usually involved with via sis_class_user
         */
        $usualEventIds = $this->generateUsualEventIds($user, $startDatetime, $finishDatetime, $folderCode);

        $events = $this->eventMapper->findByColumn('id', $usualEventIds)->indexBy('id');

        /* Find a list of events which have overrides that might affect $user
         * If there are overrides, process each one specially
         */
        $overrideEventIds = $this->generateOverrideEventIds($user, $startDatetime, $finishDatetime, $folderCode);

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
            $mutatedInvolvements = $this->generateForEvent($overrideEvent);

            /* If you're still in the list after the mutations; pile it into the stack
             * Otherwise, you aren't involved anymore; remove it from the stack
             */
            if (isset($mutatedInvolvements[$user->getExternalId()])) {
                $events[$overrideEvent->getId()] = $overrideEvent;
            } else {
                unset($events[$overrideEvent->getId()]);
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
     * @param \Schoolbox\User $user
     * @param string          $startDatetime
     * @param string          $finishDatetime
     * @param string|null     $folderCode
     * @return array
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    private function generateUsualEventIds(\Schoolbox\User $user, string $startDatetime, string $finishDatetime, ?string $folderCode = null): array
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
            AND se.deleted_at IS NULL
            AND u.synergy_id = :externalId
            AND u.deleted_at IS NULL
        ";

        $params = [
            'startDatetime' => $startDatetime,
            'finishDatetime' => $finishDatetime,
            'externalId' => $user->getExternalId(),
            'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE
        ];

        /* Optionally filter by foldercode
         */
        if ('' !== (string)$folderCode) {
            $query .= " AND se.code = :folderCode";
            $params['folderCode'] = $folderCode;
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
     * usual involvement via sis_class_user
     *
     * Optionally filters to $folderCode
     *
     * @param \Schoolbox\User $user
     * @param string          $startDatetime
     * @param string          $finishDatetime
     * @param string|null     $folderCode
     * @return array
     * @throws \PDOException
     */
    private function generateOverrideEventIds(\Schoolbox\User $user, string $startDatetime, string $finishDatetime, ?string $folderCode = null): array
    {
        $query = "
            SELECT se.id
            FROM {$this->eventMapper->getTable()} se 
            INNER JOIN {$this->overrideMapper->getTable()} seuo ON se.id = seuo.event_id
            WHERE se.classification = :classification
            AND se.start_at >= :startDatetime
            AND se.finish_at <= :finishDatetime
            AND (seuo.external_id = :externalId OR seuo.action = :actionClear)
            AND se.deleted_at IS NULL
            AND seuo.deleted_at IS NULL
        ";

        $params = [
            'startDatetime' => $startDatetime,
            'finishDatetime' => $finishDatetime,
            'externalId' => $user->getExternalId(),
            'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
            'actionClear' => Model::CLEAR
        ];

        /* Optionally filter by foldercode
         */
        if ('' !== (string)$folderCode) {
            $query .= " AND se.code = :folderCode";
            $params['folderCode'] = $folderCode;
        }

        $statement = $this->overrideMapper->getDatabase()->prepare($query);
        $statement->execute($params);

        $overrideEventIds = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $overrideEventIds[$row['id']] = $row['id'];
        }

        return $overrideEventIds;
    }
}
