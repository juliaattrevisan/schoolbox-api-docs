<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar;

use Alaress\Database\Predicate\Builder;
use Alaress_Date_Format;
use Event\Source\Mapper as EventSourceMapper;
use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use Schoolbox;
use Schoolbox\Calendar\Event\Attendance\Mapper as AttendanceMapper;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Calendar\Relation\Campus as RelationCampus;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\GenericCollection;
use UnexpectedValueException;

/**
 * Class EventMapper
 *
 * Persist events and their relationships to the db
 * This should eventually subsume the library/Calendar handler
 *
 * Note:
 * There are Event Attendance methods that should be split out into an Event Attendance mapper.
 *
 * @package Schoolbox\Calendar
 * @method Event hydrate(array $row, $alias = '', $callback = null)
 * @method Event findById($identifierValue)
 * @method Event createModel()
 */
class EventMapper extends Schoolbox\Mapper implements Schoolbox\IHomepageCleanable
{
    /**
     * @return void
     * @throws Schoolbox\Mapper\DefinitionException
     * @throws InvalidArgumentException
     */
    protected function mapping()
    {
        $this->coreMapping();

        $this->hasTable('event');
        $this->hasModel(Event::class);

        $this->uses('access', self::TYPE_INTEGER);
        $this->uses('attendance', self::TYPE_INTEGER);
        $this->uses('max_attendees', self::TYPE_INTEGER);
        $this->uses('show_attendees', self::TYPE_BOOLEAN);
        $this->uses('completed', self::TYPE_INTEGER);
        $this->uses('publish_date', self::TYPE_DATETIME, null, 'publishDateTime');
        $this->joins('owner', UserMapper::class, 'id', self::TYPE_MODEL, null, 'owner', 'owner');
        $this->uses(
            'variant',
            self::TYPE_STRING,
            self::COLUMN_ENUM,
            'variant',
            [Event::VARIANT_EVENT, Event::VARIANT_TASK,]
        );

        $this->hasJoin('owner', array(
            'mapper'    => UserMapper::class,
            'this_key'  => 'owner',
            'other_key' => 'id',
        ));
    }


    /**
    * Mapping common to extending classes
    */
    protected function coreMapping()
    {
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('series_id', self::TYPE_INTEGER, null, 'seriesId');
        $this->joins('author_id', UserMapper::class, 'id', null, null, 'author', 'author');
        $this->joins('type', EventTypeMapper::class, 'eventTypeID', null, null, 'type', 'eventType');
        $this->uses('title', self::TYPE_STRING);
        $this->uses('detail', self::TYPE_STRING);
        $this->uses('location', self::TYPE_STRING);
        $this->uses('start_datetime', self::TYPE_DATETIME, null, 'startDateTime');
        $this->uses('end_datetime', self::TYPE_DATETIME, null, 'endDateTime');
        $this->uses('allday', self::TYPE_INTEGER, null, 'allDay');

        $this->hasJoin('author', array(
            'mapper'    => UserMapper::class,
            'this_key'  => 'author_id',
            'other_key' => 'id',
        ));

        $this->hasJoin('personal', array(
            'mapper'    => Relation\PersonalMapper::class,
            'this_key'  => 'id',
            'other_key' => 'event_id',
        ));

        $this->joinsVia(
            'event_folder',
            Schoolbox\Resource\Folder\Mapper::class,
            'id',
            'folder_id',
            'event_id',
            'id',
            null,
            null,
            'folderCollection'
        );

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

        $this->joinsVia(
            'event_user',
            UserMapper::class,
            'id',
            'user_id',
            'event_id',
            'id',
            null,
            null,
            'guests'
        );

        $this->hasJoin('role', array(
            'mapper'    => Relation\RoleMapper::class,
            'this_key'  => 'id',
            'other_key' => 'event_id',
        ));

        $this->hasJoin('roles', array(
            'mapper'    => Relation\RoleMapper::class,
            'this_key'  => 'id',
            'other_key' => 'event_id',
        ));

        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback('postSave', array($this, 'postSave'));
    }

    /**
     * @inheritDoc
     */
    protected function getSelectString()
    {
        $ownerMapper = $this->getJoinMapper('owner');
        $authorMapper = $this->getJoinMapper('author');
        $eventTypeMapper = $this->getJoinMapper('eventType');
        $personalMapper = $this->getJoinMapper('personal');
        $folderMapper = $this->getJoinMapper('event_folder');
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
                {$folderMapper->getColumnString('f')},
                {$campusMapper->getColumnString('ca')},
                {$roleMapper->getColumnString('r')}
        ";
    }

    /**
     * @inheritDoc
     */
    protected function getFromString()
    {
        return "
            FROM `{$this->table}`
            LEFT JOIN {$this->getJoinSql('owner', '', 'o', true)}
            LEFT JOIN {$this->getJoinSql('owner.role','o', 'ro')}
            LEFT JOIN {$this->getJoinSql('author', '', 'a', true)}
            LEFT JOIN {$this->getJoinSql('author.role', 'a', 'ra')}
            LEFT JOIN {$this->getJoinSql('eventType', '', 't', true)}
            LEFT JOIN {$this->getJoinSql('personal', '', 'p')}
            LEFT JOIN {$this->getJoinSql('event_folder', '', 'f')}
            LEFT JOIN {$this->getJoinSql('event_campus', '', 'ca')}
            LEFT JOIN {$this->getJoinSql('role', '', 'r')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return GenericCollection|Event[]
     * @throws MapperException
     * @throws Schoolbox\Mapper\DefinitionException
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $ownerMapper = $this->getJoinMapper('owner');
        $authorMapper = $this->getJoinMapper('author');
        $eventTypeMapper = $this->getJoinMapper('eventType');
        $personalMapper = $this->getJoinMapper('personal');
        $folderMapper = $this->getJoinMapper('event_folder');
        $campusMapper = $this->getJoinMapper('event_campus');
        $roleMapper = $this->getJoinMapper('role');
        /** @var Schoolbox\RoleMapper $userRoleMapper */
        $userRoleMapper = $this->getJoinMapper('owner.role');

        /* Hydrate or die
         */
        $allEvents = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            /* Assemble event pieces
             */
            if (!isset($allEvents[$row['id']])) {
                $allEvents[$row['id']] = $this->hydrate($row);

                $allEvents[$row['id']]->setUserCollection(new Collection());
                $allEvents[$row['id']]->setFolderCollection(new Collection());
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
            $folder = $folderMapper->hydrate($row, 'f');

            if ($folder instanceof Schoolbox\Resource\Folder\Model && $folder->getId()) {
                $eventItem->getFolderCollection()->setOffset($folder->getId(), $folder);
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
     * @throws PDOException
     */
    private function removeAttendanceByEvent($event)
    {
        $query = '
            DELETE FROM event_attendance
            WHERE event_id = :eventId
        ';

        $statement = $this->database->prepare($query);
        $statement->bindValue(':eventId', $event->getId());
        $statement->execute();
    }


    /**
     * Add a users attendance for event
     *
     * @param int $eventId
     * @param int $userId
     * @throws PDOException
     */
    public function addAttendance($eventId, $userId)
    {
        $query = '
            INSERT INTO event_attendance
            VALUES (null, :eventId, :userId, :status)
        ';

        $statusPending = Event\Attendance\Model::STATUS_PENDING;

        $statement = $this->database->prepare($query);
        $statement->bindValue(':eventId', $eventId);
        $statement->bindValue(':userId', $userId);
        $statement->bindValue(':status', $statusPending);

        $statement->execute();
    }


    /**
     * Remove a users attendance for event
     *
     * @param int $eventId
     * @param int $userId
     * @throws PDOException
     */
    public function removeAttendance($eventId, $userId)
    {
        $query = '
            DELETE FROM event_attendance
            WHERE event_id = :eventId AND user_id = :userId
            ';

        $statement = $this->database->prepare($query);
        $statement->bindValue(':eventId', $eventId);
        $statement->bindValue(':userId', $userId);
        $statement->execute();
    }

    /**
     * Post-save hook method
     *
     * @param Event $event
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
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

        if (null === $seriesId) {
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
     * @throws PDOException
     */
    private function deletePermissions($event)
    {
        $query = '
            DELETE FROM event_role
            WHERE event_id = :eventId
        ';

        $statement = $this->database->prepare($query);
        $statement->bindValue(":eventId", $event->getId());
        $statement->execute();

        $query = '
            DELETE FROM event_campus
            WHERE event_id = :eventId
        ';

        $statement = $this->database->prepare($query);
        $statement->bindValue(":eventId", $event->getId());
        $statement->execute();
    }


    /**
     * Assign roles to an event
     *
     * @param Event $event
     * @throws PDOException
     */
    private function createRoles($event): void
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

            $query = '
                INSERT INTO event_role (event_id, role_id)
                VALUES (:eventId, :roleId)
                ON DUPLICATE KEY UPDATE event_id = :eventId, role_id = :roleId
            ';

            $statement = $this->database->prepare($query);
            $statement->bindValue(':eventId', $event->getId());
            $statement->bindValue(':roleId', $roleId);
            $statement->execute();
        }
    }


    /**
     * Assign campuses to an event
     *
     * @param Event $event
     * @throws PDOException
     */
    private function createCampus($event)
    {
        foreach ($event->getCampusCollection() as $campus) {
            if ($campus instanceof Campus) {
                // CampusCollection is a collection of Schoolbox\Campus instances
                // when importing events from csv or vcal
                $campusId = $campus->getId();
            } elseif ($campus instanceof RelationCampus) {
                // CampusCollection is a collection of Calendar\Relation\Campus instances
                // when modifying a series of events
                $campusId = $campus->getCampusId();
            } else {
                // CampusCollection is a collection of campus ID's
                // when editing a single event
                $campusId = $campus;
            }

            $query = '
                INSERT INTO event_campus (event_id, campus_id)
                VALUES (:eventId, :campusId)
                ON DUPLICATE KEY UPDATE event_id = :eventId, campus_id = :campusId
            ';

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
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function createSource($event)
    {
        if ($event->getSource() !== null && $event->getSource() !== "") {
            $sourceMapper = EventSourceMapper::getInstance();

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
     * @throws PDOException
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
     * @param string $startDate
     * @param string $endDate
     * @param bool   $excludeResourceBookings
     * @return Collection
     * @throws MapperException
     * @throws PDOException
     * @throws Schoolbox\Mapper\DefinitionException
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
        $statement->bindValue(':startDate', $startDate);
        $statement->bindValue(':endDate', $endDate);
        $statement->execute();

        $allEvents = $this->hydrateSelect($statement);

        return $allEvents;
    }


    /**
     * @param int                 $seriesId
     * @param Alaress_Date_Format $now
     * @return Event
     * @throws MapperException
     * @throws PDOException
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
            throw new MapperException("No events in given series");
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
     * Deletes event folder association of this folder and soft deletes event which are only for this folder
     *
     * @param int $fid
     * @throws Schoolbox\Mapper\DefinitionException
     */
    public function homepageClean($fid)
    {
        // Find events of this folder
        $folderEventQuery = "SELECT id FROM {$this->getTable()}
                        JOIN event_folder ON event_folder.event_id = event.id
                        WHERE event.deleted_at IS NULL AND folder_id = :folderId";

        $folderEvents = $this->database->query($folderEventQuery, ['folderId' => $fid])->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($folderEvents)) {
            return;
        }
        $bindings     = Builder::getArrayBindings($folderEvents);

        // Find events which is associated to this folder only
        $folderOnlyEventQuery = "SELECT event_id, count(*) AS cnt FROM {$this->getTable()}
                        JOIN event_folder ON event_folder.event_id = event.id
                        AND event.id IN ({$bindings['placeholders']})
                        GROUP BY event_id HAVING cnt = 1";

        $folderOnlyEvents = $this->database->query($folderOnlyEventQuery, $bindings['bindings'])->fetchAll(\PDO::FETCH_ASSOC);
        $eventsToDelete   = array_column($folderOnlyEvents, 'event_id');

        // Delete them
        $this->deleteByColumn('id', $eventsToDelete);

        // Delete associations
        $deleteJoinQuery = "DELETE FROM event_folder WHERE folder_id = :folderId";
        $this->database->query($deleteJoinQuery, ['folderId' => $fid]);
    }

    /**
     * Populates guests
     * @param Event $event
     * @return Event
     */
    public function populateGuests($event)
    {
        $guests = AttendanceMapper::getInstance()->findAttendances($event);
        $event->setGuests(array_column($guests, 'id'));
        return $event;
    }

    /**
     * @inheritDoc
     */
    public function diff($objectA, $objectB = null, $keys = [0, 1])
    {
        /** @var Event $objectA */
        /** @var Event $objectB */
        $objectA = $this->populateGuests($objectA);
        $diff    = parent::diff($objectA, $objectB, $keys);
        // need to explicitly add guests to diff
        if ($objectB && $objectA->getGuests() !== $objectB->getGuests()) {
            $diff['guests'] = [$objectA->getGuests(), $objectB->getGuests()];
        }
        return $diff;
    }
}
