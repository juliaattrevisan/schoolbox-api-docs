<?php
/**
 * CalendarEventMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar;
use Alaress_Date_Format;
use PDO;
use Schoolbox;
use Schoolbox\Calendar\Relation\Category;
use Schoolbox\Calendar\Relation\CategoryMapper;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Calendar\Relation\Campus as RelationCampus;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\GenericCollection;

/**
 * Class EventMapper
 *
 * @package Schoolbox\Calendar
 * @method Event hydrate(array $row, $alias = '', $callback = null)
 */
class EventMapper extends Schoolbox\Mapper implements Schoolbox\IHomepageCleanable
{
    /**
    * @return void
    */
    protected function mapping()
    {
        $this->coreMapping();

        $this->hasTable('event');
        $this->hasModel(Event::class);


        $this->uses('access',self::TYPE_INTEGER);
        $this->uses('attendance',self::TYPE_INTEGER);
        $this->uses('completed',self::TYPE_INTEGER);
        $this->uses('publish_date',self::TYPE_DATETIME,null,'publishDateTime');
        $this->joins('owner', UserMapper::class,'id',self::TYPE_MODEL, null, 'owner','owner');

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
        $this->uses('id',self::TYPE_INTEGER,self::COLUMN_IDENTIFIER);
        $this->uses('series_id',self::TYPE_INTEGER,null,'seriesId');
        $this->joins('author_id', UserMapper::class, 'id', null, null, 'author', 'author');
        $this->joins('type', EventTypeMapper::class, 'eventTypeID', null, null, 'type', 'eventType');
        $this->uses('title',self::TYPE_STRING);
        $this->uses('detail',self::TYPE_STRING);
        $this->uses('location',self::TYPE_STRING);
        $this->uses('start_datetime',self::TYPE_DATETIME,null,'startDateTime');
        $this->uses('end_datetime',self::TYPE_DATETIME,null,'endDateTime');
        $this->uses('allday',self::TYPE_INTEGER,null,'allDay');

        $this->hasJoin('author', array(
            'mapper'    => '\\Schoolbox\\UserMapper',
            'this_key'  => 'author_id',
            'other_key' => 'id',
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

        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

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
        $userRoleMapper = $this->getJoinMapper('owner.role');

        return "
			SELECT
				{$this->getColumnString()},
				{$ownerMapper->getColumnString('o')},
				{$userRoleMapper->getColumnString('ro')},
				{$authorMapper->getColumnString('a')},
				{$userRoleMapper->getColumnString('ra')},
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
			LEFT JOIN {$this->getJoinSql('owner', '', 'o', true)}
			LEFT JOIN {$this->getJoinSql('owner.role','o', 'ro')}
			LEFT JOIN {$this->getJoinSql('author', '', 'a', true)}
			LEFT JOIN {$this->getJoinSql('author.role', 'a', 'ra')}
			LEFT JOIN {$this->getJoinSql('eventType', '', 't')}
			LEFT JOIN {$this->getJoinSql('personal', '', 'p')}
			LEFT JOIN {$this->getJoinSql('category', '', 'c')}
			LEFT JOIN {$this->getJoinSql('event_campus', '', 'ca')}
			LEFT JOIN {$this->getJoinSql('role', '', 'r')}
        ";
    }


    /**
     * @param \PDOStatement $statement
     * @return GenericCollection|Event[]
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $ownerMapper = $this->getJoinMapper('owner');
        $authorMapper = $this->getJoinMapper('author');
        $eventTypeMapper = $this->getJoinMapper('eventType');
        $personalMapper = $this->getJoinMapper('personal');
        $categoryMapper = $this->getJoinMapper('category');
        $campusMapper = $this->getJoinMapper('event_campus');
        $roleMapper = $this->getJoinMapper('role');
        $userRoleMapper = $this->getJoinMapper('owner.role');

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
            $ownerItem->setRole($userRoleMapper->hydrate($row, 'ro'));
            $eventItem->setOwner($ownerItem);

            /* Author
             */
            /** @var User $authorItem */
            $authorItem = $authorMapper->hydrate($row, 'a');
            $authorItem->setRole($userRoleMapper->hydrate($row, 'ra'));
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

        return $this->createCollection($allEvents);
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
     * Add a users attendance for event
     *
     * @param $eventId
     * @param $userId
     */
    public function addAttendance($eventId, $userId)
    {
        $query = "
			INSERT INTO event_attendance
			VALUES (null, :eventId, :userId, :status, :intention)
		";

        $statusPending = Event::ATTENDANCE_STATUS_PENDING;
        $intentPresent = Event::ATTENDANCE_INTENTION_PRESENT;

        $statement = $this->database->prepare($query);
        $statement->bindParam(":eventId", $eventId);
        $statement->bindParam(":userId", $userId);
        $statement->bindParam(":status", $statusPending);
        $statement->bindParam(":intention", $intentPresent);

        $statement->execute();
    }


    /**
     * Remove a users attendance for event
     *
     * @param $eventId
     * @param $userId
     */
    public function removeAttendance($eventId, $userId)
    {
        $query = "
			DELETE FROM event_attendance
			WHERE event_id = :eventId AND user_id = :userId
			";

        $statement = $this->database->prepare($query);
        $statement->bindParam(":eventId", $eventId);
        $statement->bindParam(":userId", $userId);
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
        $this->createSource($event);
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
        $statement->bindValue(":eventId", $event->getId());
        $statement->execute();

        $query = "
			DELETE FROM event_campus
			WHERE event_id = :eventId
		";

        $statement = $this->database->prepare($query);
        $statement->bindValue(":eventId", $event->getId());
        $statement->execute();
    }


    /**
     * Assign roles to an event
     *
     * @param Event $event
     */
    private function createRoles($event)
    {
        // getRoleCollection can return ids, Schoolbox/Roles or Calendar Roles
        foreach ($event->getRoleCollection() as $role) {
            if ($role instanceof Role) {
                $roleId = $role->getId();
            } elseif ($role instanceof Schoolbox\Calendar\Relation\Role) {
                $roleId = $role->getRoleId();
            } else {
                $roleId = $role;
            }

            $query = "
				INSERT INTO event_group (event_id, group_id)
				VALUES (:eventId, :groupId)
				ON DUPLICATE KEY UPDATE event_id = :eventId, group_id = :groupId
			";

            $statement = $this->database->prepare($query);
            $statement->bindParam(":eventId", $event->getId());
            $statement->bindParam(":groupId", $roleId);
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
            $statement->bindValue(":eventId", $event->getId());
            $statement->bindValue(":campusId", $campusId);
            $statement->execute();
        }
    }

    /**
     * Assign source to an event
     *
     * @param Event $event
     * @throws Exception
     */
    private function createSource($event)
    {
        if ($event->getSource() != NULL && $event->getSource() != ""){
            $sourceMapper = \Event\Source\Mapper::getInstance();

            $source = $sourceMapper->createModel();
            $source->setCreatedAt($event->getCreatedAt());
            $source->setSourceName($event->getSource());
            $source->setUpdatedAt($event->getUpdatedAt());
            $source->setEvent($event);

            $sourceMapper->save($source);
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
    * @param $excludeResourceBookings
    * @return Collection
    */
    public function findEventsBetween($startDate, $endDate, bool $excludeResourceBookings = false)
    {
        $query = $this->getSelectString();
        $query .= $this->getFromString();

        if ($excludeResourceBookings) {
            $query .= ' LEFT JOIN resource_booking ON event.id = resource_booking.event_id ';
        }

        $query .= '
            WHERE DATE(start_datetime) >= :startDate
            AND DATE(end_datetime) <= :endDate
            AND event.deleted_at IS NULL
        ';

        if ($excludeResourceBookings) {
            $query .= ' AND resource_booking.id IS NULL ';
        }

        $statement = $this->database->prepare($query);
        $statement->bindParam(":startDate", $startDate);
        $statement->bindParam(":endDate", $endDate);
        $statement->execute();

        $allEvents = $this->hydrateSelect($statement);

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
            AND e.deleted_at IS NULL
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

    /**
     * This method needs to be defined in this base mapper, because
     * `\Schoolbox\Calendar\EventRecurrenceMapper` uses it.
     *
     * TODO extricate EOTC events from regular events, and remove this
     *
     * @param mixed $metadata
     * @param mixed $event
     */
    public function updateMetadata($metadata, $event)
    {
        // noop
    }

    /**
     * Removes all events from this folder ID
     * @param int $cid
     * @throws Exception
     * @throws Exception\Deleted
     */
    public function homepageClean($cid)
    {
        // Get ids of all events which do not belong to
        // any other folder other than the current one
        $folderSpecificEventIds = $this->getFolderSpecificEventIds($cid);

        // deleteByColumn doesn't work as expected when querying a join column,
        // because the delete string doesn't contain the joins the select does.
        // Fortunately delete(findByColumn()) works.
        $this->getJoinMapper('category')->deleteByColumn('category_id', $cid);

        if (!empty($folderSpecificEventIds)) {
            // soft delete events do not belong to any folder; if there is any
            $this->deleteEvents($folderSpecificEventIds);
        }
    }

    /**
     * Returns ids of events which only belong to the
     * folder and not any other ones (if there is any).
     *
     * @param int $cid
     * @return array
     */
    private function getFolderSpecificEventIds(int $cid): array
    {
        $folderSpecificEventIds = [];
        $eventIds = [];

        /** @var CategoryMapper $folderEventMapper */
        $folderEventMapper = $this->getJoinMapper('category');

        $folderEventsForFolder = $folderEventMapper->findByColumn('category_id', $cid);
        foreach ($folderEventsForFolder as $folderEvent){
            /** @var Category $folderEvent */
            $eventIds[] = $folderEvent->getEventId();
        }

        $folderEvents = $folderEventMapper->findByColumn('event_id', $eventIds);

        $folderEventsPerFolder = $folderEvents->groupByCallback(static function(Category $folderEvent){
            return $folderEvent->getEventId();
        });

        foreach($folderEventsPerFolder as $eventId => $folderEventsForFolder){
            if($folderEventsForFolder->count() === 1){
                $folderSpecificEventIds[] = $eventId;
            }
        }

        return $folderSpecificEventIds;
    }

    /**
     * @param array $eventIds
     * @throws Exception
     * @throws Exception\Deleted
     */
    private function deleteEvents(array $eventIds): void
    {
        foreach ($eventIds as $eventId) {
            $this->softDelete($this->findById($eventId));
        }
    }
}
