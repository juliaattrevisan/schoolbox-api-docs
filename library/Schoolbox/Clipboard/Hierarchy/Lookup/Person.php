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
        $statement = $this->userMapper->getDatabase()->executeQuery(
            "
            SELECT u.synergy_id, u.id
            FROM {$this->userMapper->getTable()} u
            INNER JOIN groups g ON g.id = u.group_id
            WHERE g.group_type = :group_type
            AND u.deleted_at IS NULL
            AND u.synergy_id IS NOT NULL
        ",
            [
                'group_type' => Role::STAFF
            ]
        );

        foreach ($statement as $user) {
            $lookup[(string)$user['synergy_id']] = $user['id'];
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
