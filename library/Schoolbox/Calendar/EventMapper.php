<?php
/**
 * CalendarEventMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Calendar;
use Schoolbox;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;


class EventMapper extends Schoolbox\External\Mapper {
	const ACCESS_PERSONAL = 1;
	const ACCESS_GROUP = 2;
	const ACCESS_CAMPUS = 3;


	/**
	 * @return void
	 */
	protected function mapping() {
		$this->coreMapping();

		$this->hasTable('event');
		$this->hasModel('\\Schoolbox\\Calendar\\Event');

		$this->hasMap(array(
			'column' => 'owner',
			'property' => 'owner',
			'type' => self::TYPE_MODEL,
			'join' => 'owner',
		));
		$this->hasMap(array(
			'column' => 'access',
			'property' => 'access',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'attendance',
			'property' => 'attendance',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'completed',
			'property' => 'completed',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'publish_date',
			'property' => 'publishDateTime',
			'type' => self::TYPE_DATETIME,
		));
		$this->hasJoin('owner', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'owner',
			'other_key' => 'id',
		));
	}


	/**
	 * Mapping common to extending classes
	 */
	protected function coreMapping() {
		$this->hasMap(array(
			'column' => 'id',
			'property' => 'id',
			'type' => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column' => 'series_id',
			'property' => 'seriesId',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'author_id',
			'property' => 'author',
			'type' => self::TYPE_MODEL,
			'join' => 'author',
		));
		$this->hasMap(array(
			'column' => 'type',
			'property' => 'type',
			'type' => self::TYPE_MODEL,
			'join' => 'eventType'
		));
		$this->hasMap(array(
			'column' => 'title',
			'property' => 'title',
			'type' => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column' => 'detail',
			'property' => 'detail',
			'type' => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column' => 'location',
			'property' => 'location',
			'type' => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'   => 'startDate',
			'property' => 'startDate',
			'type'     => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'   => 'startTime',
			'property' => 'startTime',
			'type'     => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'   => 'endDate',
			'property' => 'endDate',
			'type'     => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'   => 'endTime',
			'property' => 'endTime',
			'type'     => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column' => 'allday',
			'property' => 'allDay',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasJoin('author', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'author_id',
			'other_key' => 'id',
		));
		$this->hasJoin('eventType', array(
			'mapper'    => '\\Schoolbox\\Calendar\\EventTypeMapper',
			'this_key'  => 'type',
			'other_key' => 'eventTypeID',
		));
		$this->hasJoin('personal', array(
			'mapper'    => '\\Schoolbox\\Calendar\\Relation\\PersonalMapper',
			'this_key'  => 'id',
			'other_key' => 'event_id',
		));
		$this->hasJoin('category', array(
			'mapper'    => '\\Schoolbox\\Calendar\\Relation\\CategoryMapper',
			'this_key'  => 'id',
			'other_key' => 'event_id',
		));
		$this->hasJoin('campus', array(
			'mapper'    => '\\Schoolbox\\Calendar\\Relation\\CampusMapper',
			'this_key'  => 'id',
			'other_key' => 'event_id',
		));
		$this->hasJoin('role', array(
			'mapper'    => '\\Schoolbox\\Calendar\\Relation\\RoleMapper',
			'this_key'  => 'id',
			'other_key' => 'event_id',
		));

		$this->hasJoin('roles', array(
			'mapper'    => '\\Schoolbox\\Calendar\\Relation\\RoleMapper',
			'this_key'  => 'id',
			'other_key' => 'event_id',
		));

		$this->registerCallback('postSave', array($this, 'postSave'));
	}

	/**
	 * Custom findById
	 *
	 * @param mixed $identifier
	 * @return Event
	 * @throws \Exception
	 */
	public function findById($identifier) {
		$ownerMapper = $this->getJoinMapper('owner');
		$authorMapper = $this->getJoinMapper('author');
		$eventTypeMapper = $this->getJoinMapper('eventType');
		$personalMapper = $this->getJoinMapper('personal');
		$categoryMapper = $this->getJoinMapper('category');
		$campusMapper = $this->getJoinMapper('campus');
		$roleMapper = $this->getJoinMapper('role');

		$query = "
			SELECT
				{$this->getColumnString('e')},
				{$ownerMapper->getColumnString('o')},
				{$authorMapper->getColumnString('a')},
				{$eventTypeMapper->getColumnString('t')},
				{$personalMapper->getColumnString('p')},
				{$categoryMapper->getColumnString('c')},
				{$campusMapper->getColumnString('ca')},
				{$roleMapper->getColumnString('r')}
			FROM `{$this->table}` e
			LEFT JOIN {$this->getJoinSql('owner', 'e', 'o')}
			LEFT JOIN {$this->getJoinSql('author', 'e', 'a')}
			LEFT JOIN {$this->getJoinSql('eventType', 'e', 't')}
			LEFT JOIN {$this->getJoinSql('personal', 'e', 'p')}
			LEFT JOIN {$this->getJoinSql('category', 'e', 'c')}
			LEFT JOIN {$this->getJoinSql('campus', 'e', 'ca')}
			LEFT JOIN {$this->getJoinSql('role', 'e', 'r')}
			WHERE e.id = :eventId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $identifier);
		$statement->execute();

		/* Hydrate or die
		 */
		$personalCollection = new Collection();
		$categoryCollection = new Collection();
		$campusCollection = new Collection();
		$roleCollection = new Collection();

		while ($row = $statement->fetch()) {
			$eventItem = $this->hydrate($row, 'e');

			/* Owner
			 */
			$ownerItem = $ownerMapper->hydrate($row, 'o');
			$eventItem->setOwner($ownerItem);

			/* Author
			 */
			$authorItem = $authorMapper->hydrate($row, 'a');
			$eventItem->setAuthor($authorItem);

			/* Event Type
			 */
			$eventTypeItem = $eventTypeMapper->hydrate($row, 't');
			$eventItem->setType($eventTypeItem);

			/* Personal de-dupe
			 */
			$personal = $personalMapper->hydrate($row, 'p');
			$personalCollection->setOffset($personal->getUserId(), $personal);

			/* Category de-dupe
			 */
			$category = $categoryMapper->hydrate($row, 'c');
			$categoryCollection->setOffset($category->getCategoryId(), $category);

			/* Campus de-dupe
 			 */
			$campus = $campusMapper->hydrate($row, 'ca');
			$campusCollection->setOffset($campus->getCampusId(), $campus);

			/* Role de-dupe
 			 */
			$role = $roleMapper->hydrate($row, 'r');
			$roleCollection->setOffset($role->getRoleId(), $role);
		}

		/* If an event was found matching this id
		 */
		if ($eventItem) {
			$personalCollection->reindex();
			$eventItem->setUserCollection($personalCollection);

			$categoryCollection->reindex();
			$eventItem->setCategoryCollection($categoryCollection);

			$campusCollection->reindex();
			$eventItem->setCampusCollection($campusCollection);

			$roleCollection->reindex();
			$eventItem->setRoleCollection($roleCollection);

			return $eventItem;
		}

		throw new Exception('Instance of object not found');
	}

	/**
	 * Remove all attendance relations for $event
	 *
	 * @param Event $event
	 */
	private function removeAttendanceByEvent($event) {
		$query = "
			DELETE FROM event_attendance
			WHERE event_id = :eventId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $event->getId());
		$statement->execute();
	}

	/**
	 * Post-save hook method
	 *
	 * @param Event $event
	 */
	protected function postSave($event) {

		// It's a bit late to check this in the post save event!
		// Besides, this is the wrong place to check permissions.
//		/* Deal with groupList and campusList separately
//		 */
//		$permissionLevel = $this->getPerm($event, $_SESSION['user']);
//
//		if ($permissionLevel < WRITE_ACCESS) {
//			error(34);
//		}

		/* Clear bugalugs attendance if it has changed from true to false
		 */
		if ($event->getAttendanceDirty()) {
			 $this->removeAttendanceByEvent($event);
		}

		$this->deletePermissions($event);
		$this->createRoles($event);
		$this->createCampus($event);
	}

	/**
	 * Get all the events in the series
	 *
	 * @param Event $event
	 * @return Collection|Event[]
	 */
	public function getAllInSeries($event) {
		return $this->findByColumnList(array(
			'series_id' => $event->getSeriesId(),
		),
		array(
			'order' => array(
				'startDate' => 'ASC'
			)
		));
	}

	/**
	 * Remove all role and campus associations to an event
	 * Note that "eventGroup" means "eventRole"
	 *
	 * @param Event $event
	 */
	private function deletePermissions($event) {
		$query = "
			DELETE FROM event_group
			WHERE event_id = :eventId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $event->getId());
		$statement->execute();

		$query = "
			DELETE FROM event_campus
			WHERE event_id = :eventId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $event->getId());
		$statement->execute();
	}


	/**
	 * Assign roles to an event
	 *
	 * @param Event $event
	 * @throws Exception
	 */
	private function createRoles($event) {
		foreach ($event->getRoleCollection() as $role) {
			if($role instanceof Role) {
				$role = $role->getId();
			}

			$query = "
				INSERT INTO event_group (event_id, group_id)
				VALUES (:eventId, :groupId)
				ON DUPLICATE KEY UPDATE event_id = :eventId, group_id = :groupId
			";

			$statement = $this->database->prepare($query);
			$statement->bindParam(":eventId", $event->getId());
			$statement->bindParam(":groupId", $role);
			$statement->execute();
		}
	}


	/**
	 * Assign campuses to an event
	 *
	 * @param Event $event
	 * @throws Exception
	 */
	private function createCampus($event) {
		foreach ($event->getCampusCollection() as $campus) {
			if($campus instanceof Campus) {
				$campus = $campus->getId();
			}

			$query = "
				INSERT INTO event_campus (event_id, campus_id)
				VALUES (:eventId, :campusId)
				ON DUPLICATE KEY UPDATE event_id = :eventId, campus_id = :campusId
			";

			$statement = $this->database->prepare($query);
			$statement->bindParam(":eventId", $event->getId());
			$statement->bindParam(":campusId", $campus);
			$statement->execute();
		}
	}

	/**
	 * Get a permission level for a particular event and user combination
	 *
	 * @deprecated is this function even used?
	 * @param Event $event
	 * @param User $user
	 * @return int
	 */
	private function getPerm($event, $user) {

		/* Superuser exit
		 */
		if ($user->isSuperuser()) {
			return UserAccess::ACCESS_FULL;
		}

		/* Personal events
		 */
		if ($event->isPersonalLevel()) {

			/* If you personally spawned this event, you can modify it
			 */
			if ($event->getAuthor()->getId() == $user->getId()) {
				return UserAccess::ACCESS_WRITE;
			}
			/* Otherwise, if this event is just related to you in some way,
			 * you can only read it unless you are a staff member
			 */
			else if ($event->getOwner() == $user->getId()) {
				return $user->getRole()->getType() === 'staff' ? UserAccess::ACCESS_WRITE : UserAccess::ACCESS_READ;
			}
		}
		/* Group events
		 */
		else if ($event->isGroupLevel()) {
			$groupHandler = new \Group();

			return $groupHandler->getPermission($event->getServiceId());
		}
		/* Campus events
		 */
		else if ($event->isCampusLevel()) {
			$groupHandler = new \Group();

			/* Find the highest permission level
			 */
			$maxPermission = UserAccess::ACCESS_DENY;

			foreach ($event->getCampusCollection() as $currentCampus) {
				$permission = $groupHandler->getPermission($currentCampus);

				if ($permission > $maxPermission) {
					$maxPermission = $permission;
				}
			}

			return $maxPermission;
		}

		/* If you somehow don't fit into any of the above,
		 * you just...don't get anything.
		 */
		return UserAccess::ACCESS_DENY;
	}

	/**
	 * Prevent overlap on default event table
	 *
	 * @return int
	 */
	public function getUpperSeriesIndex() {
		$query = "
			SELECT COALESCE(MAX(series_id), 0)+1 AS series_id
			FROM `{$this->table}` e
		";

		$statement = $this->database->prepare($query);
		$statement->execute();

		$row = $statement->fetch();

		return $row['series_id'];
	}

	/**
	 * Find all the events between two dates, inclusive
	 *
	 * @param $startDate
	 * @param $endDate
	 * @return Collection
	 */
	public function findEventsBetween($startDate, $endDate) {
		$authorMapper = $this->getJoinMapper('author');
		$eventTypeMapper = $this->getJoinMapper('eventType');

        $personalMapper = $this->getJoinMapper('personal');
        $categoryMapper = $this->getJoinMapper('category');

		$query = "
			SELECT
				{$this->getColumnString('e')},
				{$authorMapper->getColumnString('a')},
				{$eventTypeMapper->getColumnString('t')},
				GROUP_CONCAT(DISTINCT luCampus.name ORDER BY luCampus.name ASC SEPARATOR ', ') AS aux_event_campuses
			FROM `{$this->table}` e
			LEFT JOIN {$this->getJoinSql('author', 'e', 'a')}
			LEFT JOIN {$this->getJoinSql('eventType', 'e', 't')}
			LEFT JOIN event_campus ON e.id = event_campus.event_id
			LEFT JOIN luCampus ON event_campus.campus_id = luCampus.id
			WHERE startDate >= :startDate
			AND endDate <= :endDate
			GROUP BY e.id
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":startDate", $startDate);
		$statement->bindParam(":endDate", $endDate);
		$statement->execute();

		/* Hydrate or die
		 */
		$allEvents = new Collection();

		while ($row = $statement->fetch()) {
			$event = $this->hydrate($row, 'e');
			$author = $authorMapper->hydrate($row, 'a');
			$eventType = $eventTypeMapper->hydrate($row, 't');

			$event->setAuthor($author);
			$event->setType($eventType);

            /* Find the groups
             */
            $event->setUserCollection($personalMapper->findAllForEvent($event));
            $event->setCategoryCollection($categoryMapper->findAllForEvent($event));

			$augmented = array(
				'event' => $event,
				'auxiliary' => array(
					'campuses' => $row['aux_event_campuses'],
				)
			);

			$allEvents->add($augmented);
		}
        new \Alaress_Probe($allEvents);
		return $allEvents;
	}
}