<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event;

use Schoolbox\GenericCollection;

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
     * @param Mapper          $eventMapper
     * @param Campus\Mapper   $campusMapper
     * @param Roletype\Mapper $roletypeMapper
     */
    public function __construct(
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Campus\Mapper $campusMapper,
        \Schoolbox\External\Event\Roletype\Mapper $roletypeMapper
    ) {
        $this->eventMapper = $eventMapper;
        $this->campusMapper = $campusMapper;
        $this->roletypeMapper = $roletypeMapper;
    }


    /**
     * Find all the relevant SUDS Events for $user
     *    - The event is associated with one or more of $user's campuses, and
     *    - The event is associated with the $user's roletype
     *
     * @param \Schoolbox\User $user
     * @return GenericCollection
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function findForUser(\Schoolbox\User $user): GenericCollection
    {

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
         */
        $query = "
            SELECT DISTINCT *
            FROM {$this->eventMapper->getTable()} se
            LEFT JOIN {$this->campusMapper->getTable()} sec ON se.id = sec.event_id
            LEFT JOIN {$this->roletypeMapper->getTable()} ser ON se.id = ser.event_id
            WHERE {$campusWhere} AND {$roleWhere}
            ORDER BY se.start_at
        ";

        $statement = $this->eventMapper->getDatabase()->prepare($query);
        $statement->execute(array_merge($campusParams, $roleParams));

        $stack = $this->eventMapper->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $stack->add($this->eventMapper->hydrate($row));
        }

        return $stack;
    }
}
