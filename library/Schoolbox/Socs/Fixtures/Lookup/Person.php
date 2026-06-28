<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Fixtures\Lookup;

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
     * @var array<string, array{
     *     id:int|string,
     *     external_id:string,
     *     firstname:string,
     *     lastname:string,
     *     preferred_name:string
     * }>|null
     */
    protected ?array $lookup = null;


    public function __construct(private \Schoolbox\UserMapper $userMapper)
    {
    }


    /**
     * Get a list of (external_id, user_id) tuples
     *
     * @return array<string, array{
     *     id:int|string,
     *     external_id:string,
     *     firstname:string,
     *     lastname:string,
     *     preferred_name:string
     * }>
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
            SELECT u.synergy_id, u.id, u.firstname, u.lastname, u.preferred_name
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
            $lookup[(string)$user['synergy_id']] = [
                'id' => $user['id'],
                'external_id' => $user['synergy_id'],
                'firstname' => $user['firstname'],
                'lastname' => $user['lastname'],
                'preferred_name' => $user['preferred_name'],
            ];
        }

        $statement = $this->userMapper->getDatabase()->query("
            SELECT synergy_id, id, firstname, lastname, preferred_name
            FROM {$this->userMapper->getTable()}
            WHERE deleted_at IS NULL
            AND synergy_id IS NOT NULL
        ");

        $statement->execute();

        while ($row = $statement->fetch()) {
            /* Find the rest of the users
             */
            if (!isset($lookup[$row['synergy_id']])) {
                $lookup[(string)$row['synergy_id']] = [
                    'id' => $row['id'],
                    'external_id' => $row['synergy_id'],
                    'firstname' => $row['firstname'],
                    'lastname' => $row['lastname'],
                    'preferred_name' => $row['preferred_name'],
                ];
            }
        }
        /* Remember this for later
         */
        $this->lookup = $lookup;

        return $lookup;
    }

    /**
     * Find a user id based on their external ID.
     * @param string $externalId
     * @return ?array{
     *      id:int|string,
     *      external_id:string,
     *      firstname:string,
     *      lastname:string,
     *      preferred_name:string
     *      }
     */
    public function fetchUser(string $externalId): ?array
    {
        if (null === $this->lookup) {
            $this->lookup = $this->generate();
        }

        return $this->lookup[$externalId] ?? null;
    }
}
