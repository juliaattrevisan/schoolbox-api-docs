<?php
/**
 * EventMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;

use Alaress_Date_Format;
use EOTC\ActivityLog\Mapper as ActivityLogMapper;
use EOTC\ListGroup\Collection as ListGroupCollection;
use Schoolbox\Calendar\EventMapper as CalendarEventMapper;
use Schoolbox\Calendar\EventType;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class EventMapper
 * @method Event hydrate(array $row, $alias = '', $callback = null)
 * @method Event findById($identifierValue)
 */
class EventMapper extends CalendarEventMapper
{
    /**
     * Calls for the core mapping of events, then extends it
     */
    protected function mapping()
    {
        $this->coreMapping();

        $this->hasTable('eotc_event');
        $this->hasModel(Event::class);

        $this->joins('teacher_in_charge_id', UserMapper::class, 'id', null, null, 'teacherInCharge', 'teacherInCharge');
    }

    /**
     * @return string
     */
    protected function getSelectString()
    {
        $authorMapper = $this->getJoinMapper('author');
        $eventTypeMapper = $this->getJoinMapper('eventType');
        $teacherInChargeMapper = $this->getJoinMapper('teacherInCharge');

        return "
            SELECT
                {$this->getColumnString()},
                {$authorMapper->getColumnString('a')},
                {$teacherInChargeMapper->getColumnString('c')},
                {$eventTypeMapper->getColumnString('t')}
        ";
    }

    /**
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM `{$this->table}`
            LEFT JOIN {$this->getJoinSql('author', '', 'a')}
            LEFT JOIN {$this->getJoinSql('teacherInCharge', '', 'c')}
            LEFT JOIN {$this->getJoinSql('eventType', '', 't')}
        ";
    }

    /**
     * @param \PDOStatement $statement
     * @return \Schoolbox\GenericCollection
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $authorMapper = $this->getJoinMapper('author');
        $eventTypeMapper = $this->getJoinMapper('eventType');
        $teacherInChargeMapper = $this->getJoinMapper('teacherInCharge');

        /* Hydrate or die
         */
        /** @var Event[] $allEvents */
        $allEvents = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

            /* Assemble event pieces
             */
            $eventItem = $this->hydrate($row);

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

            /* Teacher in charge
             */
            /** @var User $teacherInChargeItem */
            $teacherInChargeItem = $teacherInChargeMapper->hydrate($row, 'c');
            $eventItem->setTeacherInCharge($teacherInChargeItem);

            /* Accumulate the metadata for this event
             * Only use the first record
             */
            $identifier = $eventItem->getId();
            $eventItem->setMetadata($this->findMetadata($identifier)->first());

            /* Accumulate campuses for this event
             */
            $eventItem->setCampusCollection($this->findCampuses($identifier));

            /* Accumulate roles for this event
             */
            $eventItem->setRoleCollection($this->findRoles($identifier));

            /* Determine current status
             */
            if ($eventItem->getMetadata()) {
                $metadataMapper = EventMetadataMapper::getInstance();
                $metadataMapper->loadJoinedModel('status', $eventItem->getMetadata());

                /* Accumulate staff attendees for this event
                 */
                $allStaff = $metadataMapper->findStaffAttendees($eventItem->getMetadata()->getId());
                $eventItem->getMetadata()->setStaffAttendees($allStaff);

                /* Accumulate student attendees for this event
                 */
                $allStudents = $metadataMapper->findStudentAttendees($eventItem->getMetadata()->getId());
                $eventItem->getMetadata()->setStudentAttendees($allStudents);

                /* Accumulate RAMS files for this event
                 */
                $allFiles = $metadataMapper->findFiles($eventItem->getMetadata()->getId());
                $eventItem->getMetadata()->setRamsFiles($allFiles);

                /* Accumulate resource bookings for this event
                 */
                $allResourceBookings = $metadataMapper->findResourceBookings($eventItem->getMetadata()->getId());
                $eventItem->getMetadata()->setResourceBookings($allResourceBookings);

                /* Activity logs
                 */
                $allActivityLogs = ActivityLogMapper::getInstance()->findByColumn(
                    'metadata_id',
                    $eventItem->getMetadata()->getId(),
                    [
                        'order' => [
                            'created_at' => 'DESC',
                        ],
                    ]
                );

                $eventItem->getMetadata()->setActivityLog($allActivityLogs);
            }
            $allEvents[] = $eventItem;
        }

        return $this->createCollection($allEvents);
    }

    /**
     * Custom findAllByListGroups
     *
     * Use $allowedStatus to only show events matching a particular
     * set of of EOTC\EventStatus ids. Leaving $allowedStatus empty
     * will spawn all events regardless of status (it will not skip
     * over any events)
     *
     * @param array               $allowedStatus an array of EOTC\EventStatus ids
     * @param Alaress_Date_Format $from
     * @param Alaress_Date_Format $to
     * @return ListGroup\Collection
     */
    public function findAllByListGroups(
        $allowedStatus = [],
        Alaress_Date_Format $from = null,
        Alaress_Date_Format $to = null
    ) {
        $authorMapper = $this->getJoinMapper('author');
        $eventTypeMapper = $this->getJoinMapper('eventType');
        $teacherInChargeMapper = $this->getJoinMapper('teacherInCharge');

        // Build a query around from/to dates
        $queryParts = [];
        $queryParams = [];
        if ($from instanceof Alaress_Date_Format && !$from->isNull()) {
            $queryParts[] = 'e.start_datetime >= :from';
            $queryParams['from'] = $from->getDatabaseDate();
        }
        if ($to instanceof Alaress_Date_Format && !$to->isNull()) {
            $queryParts[] = 'e.start_datetime <= :to';
            $queryParams['to'] = $to->getDatabaseDate();
        }

        if (!empty($queryParts)) {
            $sql = 'WHERE ' . implode(' AND ', $queryParts);
        }

        /* Looked for a way to convince GROUP BY to group records with
         * integer series_id's, but leave NULL ones individual
         *
         * Thought about using GROUP BY IFNULL(e.series_id, e.id) but it
         * didn't really work very well
         *
         * I'll just postprocess them
         */
        $query = "
            SELECT
                {$this->getColumnString('e')},
                {$authorMapper->getColumnString('a')},
                {$teacherInChargeMapper->getColumnString('c')},
                {$eventTypeMapper->getColumnString('t')}
            FROM `{$this->table}` e
            LEFT JOIN {$this->getJoinSql('author', 'e', 'a')}
            LEFT JOIN {$this->getJoinSql('teacherInCharge', 'e', 'c')}
            LEFT JOIN {$this->getJoinSql('eventType', 'e', 't')}
            {$sql}
            ORDER BY e.start_datetime
        ";

        $statement = $this->database->prepare($query);
        $statement->execute($queryParams);

        /* Postprocess this list of events based on status and
         * organise them into a collection of collections
         */
        $listGroup = new ListGroupCollection();
        $listGroup->setup();

        /* Hydrate or die
         */
        foreach ($statement as $row) {
            /** @var Event $eventItem */
            $eventItem = $this->hydrate($row, 'e');

            /* Accumulate the metadata for this event
             * Only use the first record
             */
            $eventItem->setMetadata($this->findMetadata($eventItem->getId())->first());

            /* Determine current status
             */
            if ($eventItem->getMetadata()) {
                $metadataMapper = EventMetadataMapper::getInstance();
                $metadataMapper->loadJoinedModel('status', $eventItem->getMetadata());

                /* Ignore this event, because it's not one of the Status
                 * we've chosen to include
                 */
                if (count($allowedStatus) && !in_array($eventItem->getMetadata()->getStatus()->getId(),
                        $allowedStatus)) {
                    continue;
                }

                /* Accumulate student attendees for this event
                 */
                $allStudents = $metadataMapper->findStudentAttendees($eventItem->getMetadata()->getId());
                $eventItem->getMetadata()->setStudentAttendees($allStudents);
            }

            /** @var User $teacherInChargeItem */
            $teacherInChargeItem = $teacherInChargeMapper->hydrate($row, 'c');
            $eventItem->setTeacherInCharge($teacherInChargeItem);

            /** @var EventType $eventTypeItem */
            $eventTypeItem = $eventTypeMapper->hydrate($row, 't');
            $eventItem->setType($eventTypeItem);

            /* Accumulate campuses for this event
             */
            $eventItem->setCampusCollection($this->findCampuses($eventItem->getId()));

            /* Assign it to an Event List Group
             */
            $listGroup->assign($eventItem);
        }

        /* Sort each grouping in a special way
         */
        $listGroup->sortEventsByStartDate();

        return $listGroup;
    }

    /**
     * Custom findAllByListGroups for a particular user
     *
     * This is used on the My EOTC screen
     *
     * @param User $user
     * @return ListGroup\Collection
     */
    public function findAllByListGroupsForUser($user)
    {
        $authorMapper = $this->getJoinMapper('author');
        $eventTypeMapper = $this->getJoinMapper('eventType');
        $teacherInChargeMapper = $this->getJoinMapper('teacherInCharge');

        /* Looked for a way to convince GROUP BY to group records with
         * integer series_id's, but leave NULL ones individual
         *
         * Thought about using GROUP BY IFNULL(e.series_id, e.id) but it
         * didn't really work very well
         *
         * I'll just postprocess them
         */
        $query = "
            SELECT
                {$this->getColumnString('e')},
                {$authorMapper->getColumnString('a')},
                {$teacherInChargeMapper->getColumnString('c')},
                {$eventTypeMapper->getColumnString('t')}
            FROM `{$this->table}` e
            LEFT JOIN {$this->getJoinSql('author', 'e', 'a')}
            LEFT JOIN {$this->getJoinSql('teacherInCharge', 'e', 'c')}
            LEFT JOIN {$this->getJoinSql('eventType', 'e', 't')}
            WHERE e.author_id = :userId
            OR e.teacher_in_charge_id = :userId
            ORDER BY e.start_datetime
        ";

        $statement = $this->database->prepare($query);
        $statement->execute(
            [
                'userId' => $user->getId(),
            ]
        );

        $allEvents = new ListGroupCollection();
        $processedSeriesIds = [];

        /* Hydrate or die
         */
        foreach ($statement as $row) {
            /** @var Event $eventItem */
            $eventItem = $this->hydrate($row, 'e');

            /** @var User $teacherInChargeItem */
            $teacherInChargeItem = $teacherInChargeMapper->hydrate($row, 'c');
            $eventItem->setTeacherInCharge($teacherInChargeItem);

            /** @var EventType $eventTypeItem */
            $eventTypeItem = $eventTypeMapper->hydrate($row, 't');
            $eventItem->setType($eventTypeItem);

            /* Accumulate the metadata for this event
             * Only use the first record
             */
            $eventItem->setMetadata($this->findMetadata($eventItem->getId())->first());

            /* Determine current status
             */
            if ($eventItem->getMetadata()) {
                $metadataMapper = EventMetadataMapper::getInstance();
                $metadataMapper->loadJoinedModel('status', $eventItem->getMetadata());

                /* Accumulate student attendees for this event
                 */
                $allStudents = $metadataMapper->findStudentAttendees($eventItem->getMetadata()->getId());
                $eventItem->getMetadata()->setStudentAttendees($allStudents);
            }

            /* Only add the first in a series; ignore the others
             */
            if (is_numeric($eventItem->getSeriesId())) {
                if (!in_array($eventItem->getSeriesId(), $processedSeriesIds)) {
                    $processedSeriesIds[] = $eventItem->getSeriesId();

                    $allEvents->add($eventItem);
                }
            } else {
                /* Otherwise just add the one-off event
                 */
                $allEvents->add($eventItem);
            }
        }

        return $allEvents;
    }

    /**
     * Postsave hook
     *
     * Override to prevent base postSave from occurring
     *
     * @param Event $event
     */
    protected function postSave($event)
    {
        /* noop
         */
    }

    /**
     * Some caching issue prevents metadata being correctly formed.
     *
     * It contains data from its previous incarnation (something in core mapper's save method
     * prevents joined objects from containing the correct data)
     *
     * Therefore can't rely on postSave correctly assigning people/relations
     *
     * @param EventMetadata $metadata
     * @param Event         $event
     */
    public function updateMetadata($metadata, $event)
    {
        if (!($metadata instanceof EventMetadata && $event instanceof Event)) {
            return;
        }
        $event->setMetadata($metadata);

        $metadataMapper = EventMetadataMapper::getInstance();
        $metadataMapper->save($event->getMetadata());
        $metadataMapper->saveRelation($event);

        $this->deleteCampusRelations($event);
        $this->createCampus($event);

        $this->deleteRoleRelations($event);
        $this->createRole($event);

        $metadataMapper->deleteStaffAttendeesRelations($event->getMetadata());
        $metadataMapper->createStaffAttendees($event->getMetadata());

        $metadataMapper->deleteStudentAttendeesRelations($event->getMetadata());
        $metadataMapper->createStudentAttendees($event->getMetadata());

        $metadataMapper->deleteFilesRelations($event->getMetadata());
        $metadataMapper->createFiles($event->getMetadata());

        $metadataMapper->deleteResourceBookingsRelations($event->getMetadata());
        $metadataMapper->createResourceBookings($event->getMetadata());
    }

    /**
     * Find metadata for a particular event
     *
     * @param int $identifier
     * @return Collection|EventMetadata[]
     */
    protected function findMetadata($identifier)
    {
        $manyManyRelation = new ManyManyRelation();
        $manyManyRelation->setup(
            [
                'name' => 'eotc_event_metadata',
            ],
            [
                /* Find things to join to this...
                 */
                'column'   => 'event_id',
                'value'    => $identifier,

                /* ...using this relation, such that column = key
                 */
                'relation' => [
                    'column' => 'metadata_id',
                    'key'    => 'id',
                    'mapper' => EventMetadataMapper::getInstance(),
                ],
            ]
        );

        return $manyManyRelation->find();
    }

    /**
     * Deletes metadata relations if there are no remaining
     * events in the series
     *
     * @param Event $event
     */
    public function deleteMetadataRelations($event)
    {
        $query = "
            DELETE FROM `eotc_event_metadata`
            WHERE event_id = :eventId
        ";

        $statement = $this->database->prepare($query);
        $statement->execute(
            [
                'eventId' => $event->getId(),
            ]
        );
    }

    /**
     * Assign campuses to an event
     *
     * @param Event $event
     */
    private function createCampus($event)
    {
        $query = "
            INSERT INTO `eotc_event_campus` (event_id, campus_id)
            VALUES (:eventId, :campusId)
        ";

        $statement = $this->database->prepare($query);

        foreach ($event->getCampusCollection() as $currentCampus) {
            $currentCampus = ($currentCampus instanceof Campus)
                ? $currentCampus->getId()
                : $currentCampus;

            $statement->execute(
                [
                    "eventId"  => $event->getId(),
                    "campusId" => $currentCampus,
                ]
            );
        }
    }

    /**
     * Find campuses for a particular event
     *
     * @param int $identifier
     * @return Collection|Campus[]
     */
    protected function findCampuses($identifier)
    {
        $manyManyRelation = new ManyManyRelation();
        $manyManyRelation->setup(
            [
                'name' => 'eotc_event_campus',
            ],
            [
                /* Find things to join to this...
                 */
                'column'   => 'event_id',
                'value'    => $identifier,

                /* ...using this relation, such that column = key
                 */
                'relation' => [
                    'column' => 'campus_id',
                    'key'    => 'id',
                    'mapper' => CampusMapper::getInstance(),
                ],
            ]
        );

        return $manyManyRelation->find();
    }

    /**
     * Deletes campus relations if there are no remaining
     * events in the series
     *
     * @param Event $event
     */
    public function deleteCampusRelations($event)
    {
        $query = "
            DELETE FROM `eotc_event_campus`
            WHERE event_id = :eventId
        ";

        $statement = $this->database->prepare($query);
        $statement->execute(
            [
                'eventId' => $event->getId(),
            ]
        );
    }

    /**
     * Assign roles to an event
     *
     * @param Event $event
     */
    private function createRole($event)
    {
        $query = "
            INSERT INTO `eotc_event_role` (event_id, role_id)
            VALUES (:eventId, :roleId)
        ";

        $statement = $this->database->prepare($query);

        foreach ($event->getRoleCollection() as $currentRole) {
            $currentRole = ($currentRole instanceof Role)
                ? $currentRole->getId()
                : $currentRole;

            $statement->execute(
                [
                    "eventId" => $event->getId(),
                    "roleId"  => $currentRole,
                ]
            );
        }
    }

    /**
     * Find roles for a particular event
     *
     * @param int $identifier
     * @return Collection|Role[]
     */
    protected function findRoles($identifier)
    {
        $manyManyRelation = new ManyManyRelation();
        $manyManyRelation->setup(
            [
                'name' => 'eotc_event_role',
            ],
            [
                /* Find things to join to this...
                 */
                'column'   => 'event_id',
                'value'    => $identifier,

                /* ...using this relation, such that column = key
                 */
                'relation' => [
                    'column' => 'role_id',
                    'key'    => 'id',
                    'mapper' => RoleMapper::getInstance(),
                ],
            ]
        );

        return $manyManyRelation->find();
    }

    /**
     * Deletes role relations if there are no remaining
     * events in the series
     *
     * @param Event $event
     */
    public function deleteRoleRelations($event)
    {
        $query = "
            DELETE FROM `eotc_event_role`
            WHERE event_id = :eventId
        ";

        $statement = $this->database->prepare($query);
        $statement->execute(
            [
                'eventId' => $event->getId(),
            ]
        );
    }

    /**
     * Does nothing, EOTC events aren't associated with homepages
     *
     * @param int $cid
     */
    public function homepageClean($cid)
    {
        // noop
    }
}
