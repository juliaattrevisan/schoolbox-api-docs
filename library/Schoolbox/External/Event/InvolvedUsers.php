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
     * @var Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


    /**
     * @var Overrides\Mapper
     */
    private \Schoolbox\External\Event\Overrides\Mapper $overrideMapper;


    /**
     * @var \Schoolbox\UserMapper
     */
    private \Schoolbox\UserMapper $userMapper;


    /**
     * @param \Schoolbox\External\ClassEntryMapper       $classEntryMapper
     * @param \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper
     * @param Mapper                                     $eventMapper
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
     * @param Model $event
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DebugBar\DebugBarException
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
            $usualUsers[$involvement->getExternalId()] = $this->userMapper->findByExternalId($involvement->getExternalId());
        }

        /* Remember a new set
         */
        $stack = $usualUsers;

        /* Find the overrides
         */
        $overrides = $this->overrideMapper->findByColumn('event_id', $event->getId());

        /* Decide how each $override affects the $stack set
         */
        foreach ($overrides as $override) {
            if (Overrides\Model::SUBTRACT === $override->getAction()) {
                unset($stack[$override->getUser()->getExternalId()]);
            } elseif (Overrides\Model::ADD === $override->getAction()) {
                $stack[$override->getUser()->getExternalId()] = $override->getUser();
            } elseif (Overrides\Model::CLEAR === $override->getAction()) {
                $stack = [];
            }
        }

        return $stack;
    }


    /**
     * Generate a list of events in which $user is involved
     * Also takes into account any event overrides
     *
     * @param \Schoolbox\User $user
     * @return GenericCollection
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DebugBar\DebugBarException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Schoolbox\Mapper\Exception\NotFound
     */
    public function generateForUser(\Schoolbox\User $user): GenericCollection
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
            AND se.deleted_at IS NULL
            AND u.synergy_id = :externalId
            AND u.deleted_at IS NULL
        ";

        $statement = $this->overrideMapper->getDatabase()->prepare($query);

        $statement->execute([
            'externalId' => $user->getExternalId(),
            'classification' => 'timetable'
        ]);

        $usualEventIds = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $usualEventIds[$row['id']] = $row['id'];
        }

        /* This is the default scenario for the most part; it's all the events
         * that $user is usually involved with via sis_class_user
         */
        $events = $this->eventMapper->findByColumn('id', $usualEventIds)->indexBy('id');

        /* Find a list of events which have overrides that might affect $user
         * If there are overrides, process each one specially
         */
        $query = "
            SELECT se.id
            FROM {$this->eventMapper->getTable()} se 
            INNER JOIN {$this->overrideMapper->getTable()} seuo ON se.id = seuo.event_id
            WHERE se.classification = :classification
            AND (seuo.external_id = :externalId OR seuo.action = :actionClear)
            AND se.deleted_at IS NULL
            GROUP BY se.id 
            ORDER BY se.id
        ";

        $statement = $this->overrideMapper->getDatabase()->prepare($query);

        $statement->execute([
            'externalId' => $user->getExternalId(),
            'classification' => Model::CLASSIFICATION_TIMETABLE,
            'actionClear' => Overrides\Model::CLEAR
        ]);

        $overrideEventIds = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $overrideEventIds[$row['id']] = $row['id'];
        }

        /* Figure out the mutations that were specified by the event overrides
         * and then decide if $user was still involved
         */
        $overrideEvents = $this->eventMapper->findByColumn('id', $overrideEventIds)->indexBy('id');

        foreach ($overrideEvents as $eventId => $overrideEvent) {
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
}