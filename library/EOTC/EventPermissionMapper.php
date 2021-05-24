<?php

/**
 * EventPermissionMapper
 *
 * Persist Event Permission models to the db
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC;

use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class EventPermissionMapper extends BaseMapper
{

    /**
     * Mapping
     */
    public function mapping()
    {
        $this->hasTable('eotc_permission');
        $this->hasModel(EventPermission::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->uses('is_allowed', self::TYPE_BOOLEAN, null, 'allowed');
        $this->uses('is_subscribed', self::TYPE_BOOLEAN, null, 'subscribed');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP, 'createdAt');
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP, 'updatedAt');
        $this->uses('deleted_at', self::TYPE_DATETIME, null, 'deletedAt');

        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->joins('campus_id', CampusMapper::class, 'id', null, null, 'campus', 'campus');
        $this->joins('status_id', EventStatusMapper::class, 'id', null, null, 'status', 'status');
    }

    /**
     * Delete all the permissions for a user
     *
     * @param User $user
     */
    public function deleteForUser(User $user)
    {
        $query = "
			DELETE FROM `{$this->table}`
			WHERE user_id = :userId
		";

        $statement = $this->database->prepare($query);
        $statement->execute(['userId' => $user->getId()]);
    }

    /**
     * A unique list of user ids with permissions,
     * ordered by surname
     *
     * @return array
     */
    public function findUserIds()
    {
        $query = "
			SELECT `user_id`
			FROM `{$this->table}` p
			JOIN user ON p.user_id = user.id AND user.deleted_at IS NULL
			GROUP BY user_id
			ORDER BY lastname
		";

        $statement = $this->database->prepare($query);
        $statement->execute();

        $userIds = array();

        foreach ($statement as $row) {
            $userIds[] = $row['user_id'];
        }

        return $userIds;
    }

    /**
     * Find all the permissions for a particular user
     *
     * @param User $user
     * @return GenericCollection|EventPermission[]
     */
    public function findByUser(User $user)
    {
        $query = "
			SELECT
				{$this->getColumnString('p')},
				{$this->getJoinColumnString('campus', 'c')},
				{$this->getJoinColumnString('status', 's')}
			FROM `{$this->table}` p
			LEFT JOIN {$this->getJoinSql('campus', 'p', 'c')}
			LEFT JOIN {$this->getJoinSql('status', 'p', 's')}
			WHERE p.user_id = :userId
			AND p.is_allowed IS TRUE
			ORDER BY p.campus_id, p.status_id
		";

        $statement = $this->database->prepare($query);
        $statement->execute(["userId" => $user->getId()]);
        return $this->hydrateWithCampusAndStatus($statement);
    }

    /**
     * Find all the subscriptions for a particular user.
     *
     * @param User $user
     * @return GenericCollection|EventPermission[]
     */
    public function findSubscriptionsByUser(User $user)
    {
        $query = "
			SELECT
				{$this->getColumnString('p')},
				{$this->getJoinColumnString('campus', 'c')},
				{$this->getJoinColumnString('status', 's')}
			FROM `{$this->table}` p
			LEFT JOIN {$this->getJoinSql('campus', 'p', 'c')}
			LEFT JOIN {$this->getJoinSql('status', 'p', 's')}
			WHERE p.user_id = :userId
			AND p.is_subscribed IS TRUE
			ORDER BY p.campus_id, p.status_id
		";

        $statement = $this->database->prepare($query);
        $statement->execute(["userId" => $user->getId()]);
        return $this->hydrateWithCampusAndStatus($statement);
    }

    /**
     * Helper for hydrating a statement into a list of EventPermission objects,
     * with attached campus and status.
     *
     * @param \Traversable $statement
     * @return GenericCollection|EventPermission[]
     */
    protected function hydrateWithCampusAndStatus($statement)
    {
        $allPermissions = $this->createCollection();
        foreach ($statement as $row) {
            $permissionItem = $this->hydrate($row, 'p');

            $campusItem = $this->hydrateJoin($row, 'campus', 'c');
            $permissionItem->setCampus($campusItem);

            $statusItem = $this->hydrateJoin($row, 'status', 's');
            $permissionItem->setStatus($statusItem);

            $allPermissions->add($permissionItem);
        }
        return $allPermissions;
    }
}
