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
use Alaress_Date_Format;
use PDO;
use Schoolbox;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Calendar\Relation\Campus as RelationCampus;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Class EventMapper
 *
 * @package Schoolbox\Calendar
 * @method Event hydrate(array $row, $alias = '', $callback = null)
 */
class EventMapper extends Schoolbox\Mapper
{
    const ACCESS_PERSONAL = 1;
    const ACCESS_GROUP = 2;
    const ACCESS_CAMPUS = 3;


    /**
    * @return void
    */
    protected function mapping()
    {
        $this->coreMapping();

        $this->hasTable('event');
        $this->hasModel(Event::class);

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
    protected function coreMapping()
    {
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
                'column'   => 'start_datetime',
                'property' => 'startDateTime',
                'type'     => self::TYPE_DATETIME,
        ));
        $this->hasMap(array(
                'column'   => 'end_datetime',
                'property' => 'endDateTime',
                'type'     => self::TYPE_DATETIME,
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

        $this->joinsVia(
            'event_campus',
            CampusMapper::class,
            'id',
            'campus_id',
            'event_id',
            'id',
            null,
            null,
            'campusCollection'
        );

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

    protected function getSelectString()
    {
        $ownerMapper = $this->getJoinMapper('owner');
        $authorMapper = $this->getJoinMapper('author');
        $eventTypeMapper = $this->getJoinMapper('eventType');
        $personalMapper = $this->getJoinMapper('personal');
        $categoryMapper = $this->getJoinMapper('category');
        $campusMapper = $this->getJoinMapper('event_campus');
        $roleMapper = $this->getJoinMapper('role');

        return "
			SELECT
				{$this->getColumnString()},
				{$ownerMapper->getColumnString('o')},
				{$authorMapper->getColumnString('a')},
				{$eventTypeMapper->getColumnString('t')},
				{$personalMapper->getColumnString('p')},
				{$categoryMapper->getColumnString('c')},
				{$campusMapper->getColumnString('ca')},
				{$roleMapper->getColumnString('r')}
        ";
    }

    protected function getFromString()
    {
        return "
            FROM `{$this->table}` 
			LEFT JOIN {$this->getJoinSql('owner', '', 'o')}
			LEFT JOIN {$this->getJoinSql('author', '', 'a')}
			LEFT JOIN {$this->getJoinSql('eventType', '', 't')}
			LEFT JOIN {$this->getJoinSql('personal', '', 'p')}
			LEFT JOIN {$this->getJoinSql('category', '', 'c')}
			LEFT JOIN {$this->getJoinSql('event_campus', '', 'ca')}
			LEFT JOIN {$this->getJoinSql('role', '', 'r')}
        ";
    }

    protected function hydrateSelect(\PDOStatement $statement)
    {
        $ownerMapper = $this->getJoinMapper('owner');
        $authorMapper = $this->getJoinMapper('author');
        $eventTypeMapper = $this->getJoinMapper('eventType');
        $personalMapper = $this->getJoinMapper('personal');
        $categoryMapper = $this->getJoinMapper('category');
        $campusMapper = $this->getJoinMapper('event_campus');
        $roleMapper = $this->getJoinMapper('role');

        /* Hydrate or die
		 */
        $allEvents = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

            /* Assemble event pieces
             */
            if (!isset($allEvents[$row['id']])) {
                $allEvents[$row['id']] = $this->hydrate($row);

                $allEvents[$row['id']]->setUserCollection(new Collection());
                $allEvents[$row['id']]->setCategoryCollection(new Collection());
                $allEvents[$row['id']]->setCampusCollection(new Collection());
                $allEvents[$row['id']]->setRoleCollection(new Collection());
            }

            $eventItem = $allEvents[$row['id']];

            /* Owner
             */
            /** @var User $ownerItem */
            $ownerItem = $ownerMapper->hydrate($row, 'o');
            $eventItem->setOwner($ownerItem);

            /* Author
             */
            /** @var User $authorItem */
            $authorItem = $authorMapper->hydrate($row, 'a');
            $eventItem->setAuthor($authorItem);

            /* Event Type
             */
            /** @var EventType $eventTypeItem */
            $eventTypeItem = $eventTypeMapper->hydrate($row, 't');
            $eventItem->setType($eventTypeItem);

            /* Personal de-dupe
             */
            $personal = $personalMapper->hydrate($row, 'p');

            if ($personal instanceof Relation\Personal && $personal->getUserId()) {
                $eventItem->getUserCollection()->setOffset($personal->getUserId(), $personal);
            }

            /* Category de-dupe
             */
            $category = $categoryMapper->hydrate($row, 'c');

            if ($category instanceof Relation\Category && $category->getCategoryId()) {
                $eventItem->getCategoryCollection()->setOffset($category->getCategoryId(), $category);
            }

            /* Campus de-dupe
             */
            $campus = $campusMapper->hydrate($row, 'ca');

            if ($campus instanceof Campus && $campus->getId()) {
                $eventItem->getCampusCollection()->setOffset($campus->getId(), $campus);
            }

            /* Role de-dupe
             */
            $role = $roleMapper->hydrate($row, 'r');

            if ($role instanceof Relation\Role && $role->getRoleId()) {
                $eventItem->getRoleCollection()->setOffset($role->getRoleId(), $role);
            }
        }

        return new Collection($allEvents);
    }

    /**
    * Remove all attendance relations for $event
    *
    * @param Event $event
    */
    private function removeAttendanceByEvent($event)
    {
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
    protected function postSave($event)
    {
        // Clear attendance if it has changed from true to false
        if ($event->getAttendanceDirty()) {
             $this->removeAttendanceByEvent($event);
        }

        // reset permissions
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
    public function getAllInSeries($event)
    {
        $seriesId = $event->getSeriesId();

        if (!isset($seriesId)) {
            return new Collection();
        }

        return $this->findByColumnList(
            [
                'series_id' => $seriesId,
            ],
            [
                'cache' => false,
                'order' => array(
                    'start_datetime' => 'ASC'
                )
            ]
        );
    }

    /**
    * Remove all role and campus associations to an event
    * Note that "eventGroup" means "eventRole"
    *
    * @param Event $event
    */
    private function deletePermissions($event)
    {
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
    private function createRoles($event)
    {
        foreach ($event->getRoleCollection() as $role) {
            if ($role instanceof Role) {
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
    private function createCampus($event)
    {
        foreach ($event->getCampusCollection() as $campus) {

            if ($campus instanceof Campus) {
                // CampusCollection is a collection of Schoolbox\Campus instances
                // when importing events from csv or vcal
                $campusId = $campus->getId();
            }
            elseif ($campus instanceof RelationCampus) {
                // CampusCollection is a collection of Calendar\Relation\Campus instances
                // when modifying a series of events
                $campusId = $campus->getCampusId();
            }
            else {
                // CampusCollection is a collection of campus ID's
                // when editing a single event
                $campusId = $campus;
            }

            $query = "
				INSERT INTO event_campus (event_id, campus_id)
				VALUES (:eventId, :campusId)
				ON DUPLICATE KEY UPDATE event_id = :eventId, campus_id = :campusId
			";

            $statement = $this->database->prepare($query);
            $statement->bindParam(":eventId", $event->getId());
            $statement->bindParam(":campusId", $campusId);
            $statement->execute();
        }
    }


    /**
    * Prevent overlap on default event table
    *
    * @return int
    */
    public function getUpperSeriesIndex()
    {
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
    public function findEventsBetween($startDate, $endDate)
    {
        /** @var Schoolbox\UserMapper $authorMapper */
        $authorMapper = $this->getJoinMapper('author');

        /** @var EventTypeMapper $eventTypeMapper */
        $eventTypeMapper = $this->getJoinMapper('eventType');

        /** @var Schoolbox\Calendar\Relation\PersonalMapper $personalMapper */
        $personalMapper = $this->getJoinMapper('personal');

        /** @var Schoolbox\Calendar\Relation\CategoryMapper $categoryMapper */
        $categoryMapper = $this->getJoinMapper('category');

        /** @var Schoolbox\Calendar\Relation\CampusMapper $campusMapper */
        $campusMapper = $this->getJoinMapper('campus');

        $query = "
			SELECT
				{$this->getColumnString('e')},
				{$authorMapper->getColumnString('a')},
				{$eventTypeMapper->getColumnString('t')}
			FROM `{$this->table}` e
			LEFT JOIN {$this->getJoinSql('author', 'e', 'a')}
			LEFT JOIN {$this->getJoinSql('eventType', 'e', 't')}
			WHERE DATE(start_datetime) >= :startDate
			AND DATE(end_datetime) <= :endDate
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

            /* Find the auxiliary info
             */
            $event->setUserCollection($personalMapper->findAllForEvent($event));
            $event->setCategoryCollection($categoryMapper->findAllForEvent($event));
            $event->setCampusCollection($campusMapper->findAllForEvent($event));

            $allEvents->add($event);
        }

		return $allEvents;
	}


    /**
     * @param int $seriesId
     * @param Alaress_Date_Format $now
     * @throws \Schoolbox\Mapper\Exception
     * @return Event
     */
    public function findNextInSeries($seriesId, Alaress_Date_Format $now = null)
    {
        $now = $now ?: Alaress_Date_Format::now();

        $stmt = $this->getDatabase()->prepare("
            SELECT {$this->getColumnString('e')}
            FROM `{$this->getTable()}` e
            WHERE e.start_datetime >= :now
            AND e.series_id = :sid
            ORDER BY e.start_datetime ASC
            LIMIT 0, 1
        ");

        $stmt->execute(array(
            'sid' => $seriesId,
            'now' => $now->getDatabaseDate()
        ));

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $this->hydrate($row, 'e');
        }

        // no future events for this series ID? go to the last one
        $series = $this->findByColumn('series_id', $seriesId, array(
            'order' => array('start_datetime' => 'DESC')
        ));

        if (!$series->count()) {
            throw new \Schoolbox\Mapper\Exception("No events in given series");
        }

        return $series->first();
    }
}
