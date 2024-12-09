<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Hierarchy\Lookup;

use Schoolbox\Role;

/**
 * Get a list of (external_id, user_id) tuples
 *
 * Where this is used, there is a high likelihood of a db-in-loop
 * scenario, so let's not do that - trade space for time
 */
class Person
{
    /**
     * @var \Schoolbox\UserMapper
     */
    private \Schoolbox\UserMapper $userMapper;


    /**
     * @var array|null
     */
    private ?array $lookup = null;


    /**
     * @param \Schoolbox\UserMapper $userMapper
     */
    public function __construct(\Schoolbox\UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
    }


    /**
     * Get a list of (external_id, user_id) tuples
     *
     * @return array
     * @throws \PDOException
     */
    public function generate(): array
    {
        /* Only do this once
         */
        if (null !== $this->lookup) {
            return $this->lookup;
        }

        $lookup = [];
        /* To check for user of role staff first to avoid mismatch
         */
        $pb = $this->userMapper->getPredicateBuilder();
        $pb->equals('g.group_type', Role::STAFF)
           ->isNull('deleted_at')
           ->notNull('synergy_id');

        $result = $this->userMapper->findByPredicates($pb);

        foreach ($result as $user) {
            $lookup[(string)$user->getExternalId()] = $user->getId();
        }

        $statement = $this->userMapper->getDatabase()->query("
            SELECT synergy_id, id
            FROM {$this->userMapper->getTable()}
            WHERE deleted_at IS NULL
            AND synergy_id IS NOT NULL
        ");

        $statement->execute();

        while ($row = $statement->fetch()) {
            /* Find the rest of the users
             */
            if (!isset($lookup[$row['synergy_id']])) {
                $lookup[(string)$row['synergy_id']] = $row['id'];
            }
        }
        /* Remember this for later
         */
        $this->lookup = $lookup;

        return $lookup;
    }
}
