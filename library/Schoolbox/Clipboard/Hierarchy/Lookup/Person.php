<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Hierarchy\Lookup;

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
        $lookup = [];

        $statement = $this->userMapper->getDatabase()->query("
            SELECT synergy_id, id
            FROM {$this->userMapper->getTable()}
            WHERE deleted_at IS NULL
            AND synergy_id IS NOT NULL
        ");

        $statement->execute();

        while ($row = $statement->fetch()) {
            $lookup[(string)$row['synergy_id']] = $row['id'];
        }

        return $lookup;
    }
}