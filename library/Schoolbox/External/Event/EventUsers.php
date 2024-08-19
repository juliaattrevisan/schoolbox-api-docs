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
     * @var \Schoolbox\Resource\Folder\Mapper
     */
    private \Schoolbox\Resource\Folder\Mapper $folderMapper;


    /**
     * @param \Schoolbox\External\Event\Mapper         $eventMapper
     * @param \Schoolbox\External\Event\Campus\Mapper  $campusMapper
     * @param Roletype\Mapper                          $roletypeMapper
     */
    public function __construct(
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private \Schoolbox\External\Event\Campus\Mapper $campusMapper,
        private \Schoolbox\External\Event\Roletype\Mapper $roletypeMapper
    ) {
        $this->folderMapper = \Schoolbox\Resource\Folder\Mapper::getInstance()->disableCache();
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

        return $this->findByRoletypeAndCampus($user, $startDatetime, $finishDatetime, $folderCodes);
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

        /* Perhaps controversial:
         * In order for you to be involved in an event, you have to be in
         * both the campus and the roletype
         *
         * Unions with events where you are directly involved with them
         * via sis_event_user relationship table (roughly equivalent to personal events)
         */
        $query = "
            SELECT DISTINCT
                {$this->eventMapper->getColumnString('se')}
            FROM {$this->eventMapper->getTable()} se
            LEFT JOIN {$this->campusMapper->getTable()} sec ON se.id = sec.event_id AND {$campusWhere}
            LEFT JOIN {$this->roletypeMapper->getTable()} ser ON se.id = ser.event_id AND {$roleWhere}
            WHERE se.classification = :classification
            AND se.start_at >= :startDatetime
            AND se.finish_at <= :finishDatetime

            UNION

            SELECT DISTINCT
                {$this->eventMapper->getColumnString('se')}
            FROM {$this->eventMapper->getTable()} se
            INNER JOIN sis_event_user seu ON se.id = seu.event_id
                AND se.classification = :classification
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

        /* Process folder codes separately; the megaquery kept timing out, so we
         * can bolt these on after the fact. It changes nothing in the query itself;
         * just what we ultimately produce in $stack for frontend
         */
        $tempFolderCodes = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $event = $this->eventMapper->hydrate($row, 'se');

            /* Only care about valid hydrations
             */
            if ($event instanceof \Schoolbox\External\Event\Model) {
                $stack->add($event);

                /* Remember this folder code: we need to hydrate their instances separately
                 */
                $code = trim((string)$row['se.code']);

                $tempFolderCodes[$code] = $code;
            }
        }

        /* Ignore useless folder codes
         *
         * Filter out all the empty strings, null values
         * Use a custom callback so 0 is a valid outcome
         */
        $tempFolderCodes = array_filter($tempFolderCodes, static function ($val) {
            return '' !== $val;
        });

        /* Fetch folder instances by their codes
         */
        $folders = $this->folderMapper->findAllByCode(array_keys($tempFolderCodes));

        /* Index each folder by their code(s)
         */
        $folderLookup = [];

        foreach ($folders as $folder) {
            foreach ($folder->getCodes() as $code) {
                $folderLookup[$code] = $folder;
            }
        }

        /* Assign folder instances quickly via lookup
         */
        foreach ($stack as $event) {
            $code = trim((string)$event->getCode());

            if ('' !== $code) {
                $event->setFolder($folderLookup[$code] ?? null);
            }
        }

        return $stack;
    }
}
