<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event;

use Alaress\Database\Predicate\InvalidColumnException;
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
     * @var Overrides\Mapper
     */
    private \Schoolbox\External\Event\Overrides\Mapper $overrideMapper;


    /**
     * @var \Schoolbox\Resource\Folder\Mapper
     */
    private \Schoolbox\Resource\Folder\Mapper $folderMapper;


    /**
     * @var \Schoolbox\Resource\Folder\Code\Mapper
     */
    private \Schoolbox\Resource\Folder\Code\Mapper $folderCodeMapper;


    /**
     * @param \Schoolbox\External\Event\Mapper        $eventMapper
     * @param \Schoolbox\External\Event\Campus\Mapper $campusMapper
     * @param Roletype\Mapper                         $roletypeMapper
     * @param Overrides\Mapper                        $overrideMapper
     */
    public function __construct(
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Campus\Mapper $campusMapper,
        \Schoolbox\External\Event\Roletype\Mapper $roletypeMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overrideMapper
    ) {
        $this->eventMapper = $eventMapper;
        $this->campusMapper = $campusMapper;
        $this->roletypeMapper = $roletypeMapper;
        $this->overrideMapper = $overrideMapper;
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
        $stack->merge($this->findByRoletypeAndCampus($user, $startDatetime, $finishDatetime, $folderCodes));

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
     * @param int                    $startTimestamp
     * @param int                    $endTimestamp
     * @param GenericCollection|null $folderCodes
     * @return void
     * @throws InvalidColumnException
     * @throws \PDOException
     * @throws \UnderflowException
     */
    private function findByOverrides(\Schoolbox\User $user, int $startTimestamp, int $endTimestamp, ?GenericCollection $folderCodes = null)
    {
        $startDatetime = \Alaress_Date_Format::newFromTimestamp($startTimestamp)->getDatabase();
        $finishDatetime = \Alaress_Date_Format::newFromTimestamp($endTimestamp)->getDatabase();

        /* Find a list of events which have overrides that might affect $user
         * If there are overrides, process each one specially
         */
        $overrideEventIds = $this->generateOverrideEventIds($user, $startDatetime, $finishDatetime, $folderCodes);

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
            'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_EVENT,
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
}
