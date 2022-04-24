<?php

/**
 * EventConnector
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC;

use Calendar\Event\Collection;
use Calendar\Event\Type\EOTC\Event;
use Schoolbox\Calendar\EventType;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\User;

class EventConnector extends EventMapper
{

    /**
     * The user we're getting EOTC events for
     *
     * @var User
     */
    protected $user;


    /**
     * Get details of a single event (used in Mobile)
     *
     * @param int $eventId
     *
     * @return Collection
     * @throws DefinitionException
     * @throws MapperException
     * @throws \InvalidArgumentException
     */
    public function getEvent($eventId): Collection
    {
        $authorMapper    = $this->getJoinMapper('author');
        $eventTypeMapper = $this->getJoinMapper('eventType');

        /* Include two separate counts for
         * Number of Student Attendees
         * Number of Staff Attendees (includes +1 for Teacher In Charge)
         */
        $query = "
            SELECT
                {$this->getColumnString('e')},
                {$authorMapper->getColumnString('a')},
                {$eventTypeMapper->getColumnString('t')},
            FROM `{$this->table}` e
            JOIN {$this->getJoinSql('author', 'e', 'a',true)}
            JOIN {$this->getJoinSql('eventType', 'e', 't')}
            WHERE e.deleted_at IS NULL
        ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(":eventId", $eventId);
        $statement->execute();

        $row = $statement->fetch();

        $collection = new Collection();

        if ($row) {
            /** @var Event $eventItem */
            $eventItem = $this->hydrate($row, 'e');

            /** @var User $authorItem */
            $authorItem = $authorMapper->hydrate($row, 'a');
            $eventItem->setAuthor($authorItem);

            /** @var EventType $eventTypeItem */
            $eventTypeItem = $eventTypeMapper->hydrate($row, 't');
            $eventItem->setType($eventTypeItem);

            /* Accumulate campuses for this event
             */
            $eventItem->setCampusCollection($this->findCampuses($eventItem->getId()));

            /* For filters
             */
            $campusIds = array();

            foreach ($eventItem->getCampusCollection() as $currentCampus) {
                $campusIds[] = $currentCampus->getId();
            }

            /* Translate an \EOTC\Event type into a EventInterface descendant */

            $event = new Event();
            $event->setAuthorName($eventItem->getAuthor()->getFullname());
            $event->setAuthorId($eventItem->getAuthor()->getId());
            $event->setEventId($eventItem->getId());
            $event->setStartTime($eventItem->getStartDateTime());
            $event->setEndTime($eventItem->getEndDateTime());
            $event->setCampusList(implode(',', $campusIds));

            /* EOTC Events are always of the type "EOTC" for usage in calendar filters */

            $event->setType($eventItem->getType()->getId());
            $event->setAccess($eventItem->getAccess());
            $event->setEventType($eventItem->getType()->getType());
            $event->setColour($eventItem->getType()->getColor());
            $event->setDetail($eventItem->getDetail());
            $event->setLocation($eventItem->getLocation());
            $event->setAllDay(0);

            /* If you have write access to the event. Only superusers and authors can modify an event */

            if ($this->getUser()->isSuperuser() || $this->getUser()->getId() == $event->getAuthorId()) {
                $event->setIsEditable(true);
            } else {
                $event->setIsEditable(false);
            }

            /* Accumulate the metadata for this event
             * Only use the first record
             */
            $eventItem->setMetadata($this->findMetadata($eventItem->getId())->first());

            /* Determine current status
             */
            if ($eventItem->getMetadata()) {
                $metadataMapper = EventMetadataMapper::getInstance();
                $metadataMapper->loadJoinedModel('status', $eventItem->getMetadata());
            }

            /* Derive publishDate settings for front-end classes
             * If it's approved, set the publishDate in the past
             * Otherwise, set it in the future
             */
            if ($eventItem->getMetadata()->getStatus()->isApproved()) {
                $publishDate = new \Alaress_Date_Format(0, 'U');
            } else {
                $publishDate = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'] + 1, 'U');
            }

            $event->setPublishDate($publishDate);

            $event->setAttendance(0);
            $event->setCompleted(false);

            $collection->add($event);
        }

        return $collection;
    }


    /**
     * Get events within a particular time span
     *
     * @param int $startTimestamp
     * @param int $endTimestamp
     * @return Collection
     * @throws MapperException
     * @throws DefinitionException
     * @throws \InvalidArgumentException
     */
    public function getDayEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): Collection
    {
        $authorMapper          = $this->getJoinMapper('author');
        $eventTypeMapper       = $this->getJoinMapper('eventType');
        $teacherInChargeMapper = $this->getJoinMapper('teacherInCharge');

        /* Superusers see everything
         * Skip Rejected events
         */
        $startDate = \Alaress_Date_Format::newFromTimestamp($startTimestamp)
                                         ->getUsingFormat(\Alaress_Date_Format::DATABASE_DATE_FORMAT);
        $endDate   = \Alaress_Date_Format::newFromTimestamp($endTimestamp)
                                         ->getUsingFormat(\Alaress_Date_Format::DATABASE_DATE_FORMAT);
        if ($this->getUser()->isSuperuser()) {
            $querySelect = '';
            $queryJoin = '';

            $params = [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'rejected' => EventStatusMapper::STATUS_REJECTED,
            ];
        } else {
            /* For all other user types:
             * Only show events where $user is either staff or student attendee
             *
             * This saves us from having to subquery these attendees separately,
             * by initially only getting events that are relevant to $user
             *
             * Skip Rejected events
             */
            $querySelect = "
                eotc_metadata_student.user_id as studentUserId,
                eotc_metadata_staff.user_id as staffUserId,
            ";

            $queryJoin = "
                LEFT JOIN eotc_metadata_student ON eotc_metadata_student.`metadata_id` = eotc_metadata.`id` AND eotc_metadata_student.user_id = :studentUserId
                LEFT JOIN eotc_metadata_staff ON eotc_metadata_staff.`metadata_id` = eotc_metadata.`id` AND eotc_metadata_staff.user_id = :staffUserId
            ";

            $params = [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'studentUserId' => $this->getUser()->getId(),
                'staffUserId' => $this->getUser()->getId(),
                'rejected' => EventStatusMapper::STATUS_REJECTED,
            ];
        }

        /* Do a detection on $allowedEventTypes, if provided and meaningful
         */
        $queryEventTypes = '';

        if (null !== $allowedEventTypes && \count($allowedEventTypes)) {
            $eventTypesPb = $this->getPredicateBuilder()
                ->in('e.type', $allowedEventTypes);

            $queryEventTypes = ' AND ' . $eventTypesPb->getWhere()[0];

            $params = array_merge($params, $eventTypesPb->getParams());
        }

        /* Ram in all the pieces
         */
        $query = "
            SELECT
                {$this->getColumnString('e')},
                {$authorMapper->getColumnString('a')},
                {$teacherInChargeMapper->getColumnString('c')},
                {$eventTypeMapper->getColumnString('t')},
                {$querySelect}
                (
                    SELECT COUNT(*)
                    FROM eotc_event_metadata
                    LEFT JOIN eotc_metadata ON eotc_metadata.id = eotc_event_metadata.metadata_id
                    LEFT JOIN eotc_metadata_student ON eotc_metadata_student.metadata_id = eotc_metadata.id
                    WHERE eotc_event_metadata.event_id = e.id
                    AND eotc_metadata_student.user_id IS NOT NULL
                ) AS numStudents,
                (
                    SELECT COUNT(*)+1
                    FROM eotc_event_metadata
                    LEFT JOIN eotc_metadata ON eotc_metadata.id = eotc_event_metadata.metadata_id
                    LEFT JOIN eotc_metadata_staff ON eotc_metadata_staff.metadata_id = eotc_metadata.id
                    WHERE eotc_event_metadata.event_id = e.id
                    AND eotc_metadata_staff.user_id IS NOT NULL
                ) AS numStaff
            FROM `{$this->table}` e
            JOIN {$this->getJoinSql('author', 'e', 'a', true)}
            JOIN {$this->getJoinSql('teacherInCharge', 'e', 'c', true)}
            JOIN {$this->getJoinSql('eventType', 'e', 't')}
            INNER JOIN eotc_event_metadata ON eotc_event_metadata.event_id = e.id
            INNER JOIN eotc_metadata ON eotc_metadata.id = eotc_event_metadata.metadata_id
            {$queryJoin}
            WHERE
                DATE(e.end_datetime) >= :startDate
                AND
                DATE(e.start_datetime) <= :endDate
                AND eotc_metadata.status_id != :rejected
                AND e.deleted_at IS NULL
                {$queryEventTypes}
            ORDER BY e.start_datetime, e.end_datetime
        ";

        $statement = $this->database->prepare($query);
        $statement->execute($params);

        $collection = new Collection();

        /* Anyone in the EOTC permissions sytem can also see it
         * We don't need to iterate with this, since $user doesn't
         * actually change between iterations
         *
         * EOTC Administrators and superusers always see events
         */
        $isEOTCAdministrator = false;

        $user = $this->getUser();

        if (!$user->isSuperuser()) {
            $permissionMapper = EventPermissionMapper::getInstance();
            $allPermissions   = $permissionMapper->findByUser($user);

            if ($allPermissions->count()) {
                $isEOTCAdministrator = true;
            }
        }

        foreach ($statement as $row) {

            /** @var \EOTC\Event $eventItem */
            $eventItem = $this->hydrate($row, 'e');

            /** @var User $authorItem */
            $authorItem = $authorMapper->hydrate($row, 'a');
            $eventItem->setAuthor($authorItem);

            /** @var User $teacherInChargeItem */
            $teacherInChargeItem = $teacherInChargeMapper->hydrate($row, 'c');
            $eventItem->setTeacherInCharge($teacherInChargeItem);

            /** @var EventType $eventTypeItem */
            $eventTypeItem = $eventTypeMapper->hydrate($row, 't');
            $eventItem->setType($eventTypeItem);

            /* Accumulate campuses for this event
             */
            $eventItem->setCampusCollection($this->findCampuses($eventItem->getId()));

            /* Accumulate roles for this event
             */
            $eventItem->setRoleCollection($this->findRoles($eventItem->getId()));

            /* Accumulate the metadata for this event
             * Only use the first record
             */
            $eventItem->setMetadata($this->findMetadata($eventItem->getId())->first());

            /* Determine current status
             */
            if ($eventItem->getMetadata()) {
                $metadataMapper = EventMetadataMapper::getInstance();
                $metadataMapper->loadJoinedModel('status', $eventItem->getMetadata());
            }

            /* For filters
             */
            $campusIds = [];
            $inCampus = false;
            $inRole = false;

            /* Remember if you're in at least one of the campuses chosen for this event
             */
            foreach ($eventItem->getCampusCollection() as $currentCampus) {
                $campusIds[] = $currentCampus->getId();

                if ($this->getUser()->isInCampus($currentCampus)) {
                    $inCampus = true;
                }
            }

            /* Remember if you're in at least one of the roles chosen for this event
             */
            if ($eventItem->getRoleCollection()->getBy('id', $this->getUser()->getRole()->getId()) instanceof Role) {
                $inRole = true;
            }

            /* Only present this event for a particular user if they are
             * superuser or attendee (teacher in charge, staff, student)
             * or are in the EOTC permissions system
             *
             *  - Superusers can always see it
             *  - Creators can always see it
             *  - Teachers In Charge can always see it, but is shown as translucent
             *  - Staff Attendees can always see it, but is shown as translucent
             *  - Student Attendees should only see this event if it's Approved
             *  - Anyone in both the Campus and Role of an Approved event regardless of attendee-ness
             *  - Staff in at least one of the event campuses AND status is between Approved In Principle and Approved
             */
            $isSuperuser = $user->isSuperuser();
            $isCreator = $eventItem->getAuthor()->getId() == $user->getId();
            $isTIC = $user->getId() == $eventItem->getTeacherInCharge()->getId();
            $isStaffAttendee = ($row['staffUserId'] ?? null) == $this->getUser()->getId();
            $isStudentAttendee = ($row['studentUserId'] ?? null) == $this->getUser()->getId();
            $isApproved = $eventItem->getMetadata()->getStatus()->isApproved();
            $inCampusAndRole = $inCampus && $inRole;

            /* Staff in at least one of the event campuses AND status is between Approved In Principle and Approved
             *
             * When an event transitions out of Initial Application, it is no longer just an idea but an actual
             * event that can potentially occur. Staff need to be aware of its presence so they can organise
             * events around this and not inadvertently double-book. Therefore we check whether the event is in
             * a staff member's campus; if it is, show it when it's potentially going to happen
             */
            $isInStaffCampus = $user->getRole()->isStaff() && $inCampus && $eventItem->getMetadata()->getStatus()->between(EventStatusMapper::STATUS_INPRINCIPLE, EventStatusMapper::STATUS_APPROVED);

            if ($isEOTCAdministrator || $isSuperuser || $isCreator || $isTIC || $isStaffAttendee || $isInStaffCampus || ($isApproved && ($isStudentAttendee || $inCampusAndRole))) {
                /* Translate an \EOTC\Event type into a EventInterface descendant
                 * Note that this "new Event();" does not have a mapper, so spawn concretely
                 */
                $event = new Event();
                $event->setAuthorName($eventItem->getAuthor()->getFullname());
                $event->setAuthorId($eventItem->getAuthor()->getId());
                $event->setEventId($eventItem->getId());
                $event->setStartTime($eventItem->getStartDateTime());
                $event->setEndTime($eventItem->getEndDateTime());
                $event->setAllDay($eventItem->getAllDay());
                $event->setCampusList(implode(',', $campusIds));

                /* EOTC Events are always of the type "EOTC" for usage
                 * in calendar filters
                 */
                $event->setType($eventItem->getType()->getId());

                /* Augment title with student counts
                 * students + staff + 1 TIC
                 */
                $event->setTitle("{$eventItem->getTitle()} ({$row['numStudents']},{$row['numStaff']})");

                $event->setAccess($eventItem->getAccess());
                $event->setEventType($eventItem->getType()->getType());
                $event->setColour($eventItem->getType()->getColor());
                $event->setDetail($eventItem->getDetail());
                $event->setLocation($eventItem->getLocation());

                /* If you have write access to the event. Only superusers
                 * and authors can modify an event
                 */
                if ($user->isSuperuser() || $user->getId() == $event->getAuthorId()) {
                    $event->setIsEditable(true);
                } else {
                    $event->setIsEditable(false);
                }

                $event->setAttendance(0);
                $event->setCompleted(false);

                /* Custom data
                 */
                $event->setCustom([
                    'approved' => $eventItem->getMetadata()->getStatus()->isApproved()
                ]);

                /* Derive publishDate settings for front-end classes
                 * If it's approved, set the publishDate in the past
                 * Otherwise, set it in the future
                 */
                if ($eventItem->getMetadata()->getStatus()->isApproved()) {
                    $publishDate = new \Alaress_Date_Format(0, 'U');
                } else {
                    $publishDate = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'] + 1, 'U');
                }

                $event->setPublishDate($publishDate);

                $collection->add($event);
            }
        }

        return $collection;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param  User $user
     *
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }
}
