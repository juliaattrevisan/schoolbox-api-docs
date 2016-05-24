<?php
/**
 * EventPermissionMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


class EventPermissionMapper extends \Schoolbox\Mapper {

	/**
	 * Mapping
	 */
	public function mapping() {
		$this->hasTable('eotc_permission');
		$this->hasModel('\\EOTC\\EventPermission');
		$this->hasMap(array(
			'column' => 'id',
			'property' => 'id',
			'type' => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column' => 'user_id',
			'property' => 'userId',
			'type' => self::TYPE_INTEGER,
			'join' => 'user',
		));
		$this->hasMap(array(
			'column' => 'campus_id',
			'property' => 'campusId',
			'type' => self::TYPE_MODEL,
			'join' => 'campus',
		));
		$this->hasMap(array(
			'column' => 'status_id',
			'property' => 'statusId',
			'type' => self::TYPE_INTEGER,
		));
        $this->hasMap(array(
            'column' => 'is_allowed',
            'property' => 'allowed',
            'type' => self::TYPE_BOOLEAN,
        ));
        $this->hasMap(array(
            'column' => 'is_subscribed',
            'property' => 'subscribed',
            'type' => self::TYPE_BOOLEAN,
        ));
		$this->hasMap(array(
			'column'   => 'created_at',
			'property' => 'createdAt',
			'type'     => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'   => 'updated_at',
			'property' => 'updatedAt',
			'type'     => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'   => 'deleted_at',
			'property' => 'deletedAt',
			'type'     => self::TYPE_DATETIME,
		));
		$this->hasJoin('user', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'user_id',
			'other_key' => 'id',
		));
		$this->hasJoin('campus', array(
			'mapper'    => '\\Schoolbox\\CampusMapper',
			'this_key'  => 'campus_id',
			'other_key' => 'id',
		));
		$this->hasJoin('status', array(
			'mapper'    => '\\EOTC\\EventStatusMapper',
			'this_key'  => 'status_id',
			'other_key' => 'id',
		));
	}

	/**
	 * Delete all the permissions for a user
	 *
	 * @param \Schoolbox\User $user
	 */
	public function deleteForUser(\Schoolbox\User $user) {
		$query = "
			DELETE FROM `{$this->table}`
			WHERE user_id = :userId
		";

		$statement = $this->database->prepare($query);
		$statement->execute([ 'userId' => $user->getId() ]);
	}

	/**
	 * A unique list of user ids with permissions,
	 * ordered by surname
	 *
	 * @return array
	 */
	public function findUserIds() {
		$query = "
			SELECT `user_id`
			FROM `{$this->table}` p
			LEFT JOIN user ON p.user_id = user.id
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
	 * @param \Schoolbox\User
	 * @return \Schoolbox\Collection|EventPermission[]
	 */
	public function findByUser(\Schoolbox\User $user) {
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
		$statement->bindParam(":userId", $user->getId());
		$statement->execute();
		return $this->hydrateWithCampusAndStatus($statement);
	}

    /**
     * Find all the subscriptions for a particular user.
     *
     * @param \Schoolbox\User $user
     * @return \Schoolbox\Collection|EventPermission[]
     */
    public function findSubscriptionsByUser(\Schoolbox\User $user) {
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
        $statement->bindParam(":userId", $user->getId());
        $statement->execute();
        return $this->hydrateWithCampusAndStatus($statement);
    }

    /**
     * Helper for hydrating a statement into a list of EventPermission objects,
     * with attached campus and status.
     *
     * @param \Traversable $statement
     * @return \Schoolbox\Collection|EventPermission[]
     */
    protected function hydrateWithCampusAndStatus($statement)
    {
        $allPermissions = new \Schoolbox\Collection();
        foreach ($statement as $row) {
            $permissionItem = $this->hydrate($row, 'p');

            $campusItem = $this->hydrateJoin($row, 'campus', 'c');
            $permissionItem->setCampusId($campusItem);

            $statusItem = $this->hydrateJoin($row, 'status', 's');
            $permissionItem->setStatusId($statusItem);

            $allPermissions->add($permissionItem);
        }
        return $allPermissions;
    }
}
