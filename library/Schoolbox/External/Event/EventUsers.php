<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event;

use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\External\Event\Overrides\Applicator;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;

/**
 * Get a list of events for a given user.
 *    - The event is associated with one or more of $user's campuses, and
 *    - The event is associated with the $user's roletype
 */
class EventUsers
{
    /**
     * @var Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


    /**
     * @var Campus\Mapper
     */
    private \Schoolbox\External\Event\Campus\Mapper $campusMapper;


    /**
     * @var Roletype\Mapper
     */
    private \Schoolbox\External\Event\Roletype\Mapper $roletypeMapper;


    /**
     * @var \Schoolbox\External\Profile\Staff\Mapper
     */
    private \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper;


    /**
     * @var \Schoolbox\Resource\Folder\Mapper
     */
    private \Schoolbox\Resource\Folder\Mapper $folderMapper;


    /**
     * @var \Schoolbox\Resource\Folder\Code\Mapper
     */
    private \Schoolbox\Resource\Folder\Code\Mapper $folderCodeMapper;


    /**
     * @param \Schoolbox\External\Event\Mapper         $eventMapper
     * @param \Schoolbox\External\Event\Campus\Mapper  $campusMapper
     * @param Roletype\Mapper                          $roletypeMapper
     * @param \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper
     */
    public function __construct(
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Campus\Mapper $campusMapper,
        \Schoolbox\External\Event\Roletype\Mapper $roletypeMapper,
        \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper
    ) {
        $this->eventMapper = $eventMapper;
        $this->campusMapper = $campusMapper;
        $this->roletypeMapper = $roletypeMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->folderMapper = \Schoolbox\Resource\Folder\Mapper::getInstance();
        $this->folderCodeMapper = \Schoolbox\Resource\Folder\Code\Mapper::getInstance();
    }


    /**
     * Find all the relevant SUDS Events for $user
     *    - The event is associated with one or more of $user's campuses, and
     *    - The event is associated with the $user's roletype
     *
     * @param \Schoolbox\User        $user
     * @param int                    $startTimestamp
     * @param int                    $endTimestamp
     * @param GenericCollection|null $folderCodes
     * @return GenericCollection
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function findForUser(\Schoolbox\User $user, int $startTimestamp, int $endTimestamp, ?GenericCollection $folderCodes = null): GenericCollection
    {
        $startDatetime = \Alaress_Date_Format::newFromTimestamp($startTimestamp)->getDatabase();
        $finishDatetime = \Alaress_Date_Format::newFromTimestamp($endTimestamp)->getDatabase();

        $stack = $this->eventMapper->createCollection();
        $stack = $stack->merge($this->findByRoletypeAndCampus($user, $startDatetime, $finishDatetime, $folderCodes));
        $stack = $stack->merge($this->findByOverrides($user, $startDatetime, $finishDatetime, $folderCodes));

        return $stack;
    }


    /**
     * Find events where you were involved by the (roletype, campus) combination
     * Optionally filtered by folder codes
     *
     * @param User                   $user
     * @param string                 $startDatetime
     * @param string                 $finishDatetime
     * @param GenericCollection|null $folderCodes
     * @return GenericCollection
     * @throws Exception
     * @throws InvalidColumnException
     * @throws \PDOException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    private function findByRoletypeAndCampus(
        \Schoolbox\User $user,
        string $startDatetime,
        string $finishDatetime,
        ?GenericCollection $folderCodes = null
    ): GenericCollection {
        /* Campus requirement
         */
        $campusPb = $this->campusMapper
            ->getPredicateBuilder()
            ->in('sec.campus_name', $user->getCampusCollection()->indexBy('name')->keys());

        $campusWhere = implode(' AND ', $campusPb->getWhere());
        $campusParams = $campusPb->getParams();

        /* Role requirement
         */
        $rolePb = $this->roletypeMapper
            ->getPredicateBuilder()
            ->equals('ser.roletype', $user->getRole()->getType());

        $roleWhere = implode(' AND ', $rolePb->getWhere());
        $roleParams = $rolePb->getParams();

        /* I can't join to a folder via a code (code doesn't have an identifier property)
         */
        $folderColumnString = $this->folderMapper->getColumnString('f');
        $folderCodeColumnString = $this->folderCodeMapper->getColumnString('fc');

        /* Perhaps controversial:
         * In order for you to be involved in an event, you have to be in
         * both the campus and the roletype
         */
        $query = "
            SELECT DISTINCT
                {$this->eventMapper->getColumnString('se')},
                {$folderColumnString}, 
                {$folderCodeColumnString}
            FROM {$this->eventMapper->getTable()} se
            LEFT JOIN {$this->campusMapper->getTable()} sec ON se.id = sec.event_id
            LEFT JOIN {$this->roletypeMapper->getTable()} ser ON se.id = ser.event_id
            LEFT JOIN folder_code fc ON se.code = fc.code AND fc.deleted_at IS NULL
            LEFT JOIN folder f ON fc.folder_id = f.id AND f.deleted_at IS NULL
            WHERE {$campusWhere} AND {$roleWhere}
            AND se.classification = :classification
            AND se.start_at >= :startDatetime
            AND se.finish_at <= :finishDatetime
            
            UNION
            
            SELECT DISTINCT
                {$this->eventMapper->getColumnString('se')},
                {$folderColumnString}, 
                {$folderCodeColumnString}
            FROM {$this->eventMapper->getTable()} se
            INNER JOIN sis_event_user seu ON se.id = seu.event_id
            LEFT JOIN folder_code fc ON se.code = fc.code AND fc.deleted_at IS NULL
            LEFT JOIN folder f ON fc.folder_id = f.id AND f.deleted_at IS NULL
            WHERE se.classification = :classification
            AND se.start_at >= :startDatetime
            AND se.finish_at <= :finishDatetime
            AND seu.external_id = :externalId
        ";

        $params = array_merge([
            'externalId' => $user->getExternalId(),
            'startDatetime' => $startDatetime,
            'finishDatetime' => $finishDatetime,
            'classification' => Model::CLASSIFICATION_EVENT
        ], $campusParams, $roleParams);

        $stack = $this->eventMapper->createCollection();

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
            return $stack;
        }

        $statement = $this->eventMapper->getDatabase()->prepare($query);
        $statement->execute($params);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $event = $this->eventMapper->hydrate($row, 'se');

            /* If a folder code x folder join can be found, shove it in
             */
            if ($row['f.id']) {
                /** @var \Schoolbox\Resource\Folder\Model|null $folder */
                $folder = $this->folderMapper->hydrate($row, 'f');

                /** @var \Schoolbox\Resource\Folder\Code\Model|null $folderCode */
                $folderCode = $this->folderCodeMapper->hydrate($row, 'fc');

                $folder->setCodes($this->folderCodeMapper->createCollection([$folderCode]));

                $event->setFolder($folder);
            }

            $stack->add($event);
        }

        return $stack;
    }


    /**
     * Find events where you were involved by overrides specifically
     *
     * @param User                   $user
     * @param string                 $startDatetime
     * @param string                 $finishDatetime
     * @param GenericCollection|null $folderCodes
     * @return GenericCollection
     * @throws \PDOException
     */
    private function findByOverrides(
        \Schoolbox\User $user,
        string $startDatetime,
        string $finishDatetime,
        ?GenericCollection $folderCodes = null
    ): GenericCollection {
        /* Micromanage a stack of minimal event data with overrides
         */
        $lookup = [];
        $stack = [];

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
            'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_EVENT,
            'from' => $startDatetime,
            'to' => $finishDatetime,
        ]);

        /* Collate all the overrides and group them so we can mutate everything
         * in a convenient manner
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($lookup[$row['id']])) {
                $lookup[$row['id']] = [
                    'name' => $row['title'],
                    'code' => $row['code'],
                    /* The guys who are usually involved in this event
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
            $lookup[$row['id']]['overrides'][] = [
                'external_id' => $row['external_id'],
                'action' => $row['action']
            ];
        }

        /* For every thing we found in $lookup,
         * Play back all the overrides against $usuals
         * then collate the list of surviving guys in $manifest
         */
        $overrideApplicator = new Applicator($this->staffProfileMapper);

        foreach ($lookup as $eventId => &$data) {
            $data['manifest'] = $overrideApplicator->apply($data['usuals'], $data['overrides']);

            /* Remember the event id for which you are involved via overrides
             */
            if (isset($data['manifest'][$user->getExternalId()])) {
                $stack[$eventId] = $eventId;
            }
        }

        unset($data);

        $eventIds = array_values($stack);

        return $this->eventMapper->findByColumn('id', $eventIds);
    }
}
