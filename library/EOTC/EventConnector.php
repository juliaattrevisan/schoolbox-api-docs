<?php
/**
 * EventConnector
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;

use Calendar\Event\Collection;
use Calendar\Event\Type\EOTC\Event;
use Schoolbox\Calendar\EventType;
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
     * @param $eventId
     *
     * @return Collection
     */
    public function getEvent($eventId)
    {
        $authorMapper    = $this->getJoinMapper('author');
        $eventTypeMapper = $this->getJoinMapper('eventType');

        $query = "
            SELECT
                {$this->getColumnString('e')},
                {$authorMapper->getColumnString('a')},
                {$eventTypeMapper->getColumnString('t')}
            FROM `{$this->table}` e
            LEFT JOIN {$this->getJoinSql('author', 'e', 'a')}
            LEFT JOIN {$this->getJoinSql('eventType', 'e', 't')}
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

            /* Translate an \EOTC\Event type into a
             * Calendar_Event_Type_Abstract type
             */
            $event = new Event();
            $event->setAuthorName($eventItem->getAuthor()->getFullname());
            $event->setAuthorId($eventItem->getAuthor()->getId());
            $event->setEventId($eventItem->getId());
            $event->setStartTime($eventItem->getStartDateTime());
            $event->setEndTime($eventItem->getEndDateTime());
            $event->setCampusList(implode(',', $campusIds));

            /* EOTC Events are always of the type "EOTC" for usage
             * in calendar filters
             */
            $event->setType($eventItem->getType()->getId());
            $event->setTitle($eventItem->getTitle());
            $event->setAccess($eventItem->getAccess());
            $event->setEventType($eventItem->getType()->getType());
            $event->setColour($eventItem->getType()->getColor());
            $event->setDetail($eventItem->getDetail());
            $event->setLocation($eventItem->getLocation());
            $event->setAllDay(0);

            /* If you have write access to the event. Only superusers
             * and authors can modify an event
             */
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
                $metadataMapper = new EventMetadataMapper();
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
            $event->setCompleted(0);

            $collection->add($event);
        }

        return $collection;
    }

    /**
     * Get events within a particular time span
     *
     * @param $startTimestamp
     * @param $endTimestamp
     *
     * @return Collection
     */
    public function getDayEvents($startTimestamp, $endTimestamp)
    {
        $authorMapper          = $this->getJoinMapper('author');
        $eventTypeMapper       = $this->getJoinMapper('eventType');
        $teacherInChargeMapper = $this->getJoinMapper('teacherInCharge');

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
            WHERE
                DATE(e.end_datetime) >= DATE(FROM_UNIXTIME(:startTimestamp))
                AND
                DATE(e.start_datetime) <= DATE(FROM_UNIXTIME(:endTimestamp))
            ORDER BY e.start_datetime, e.end_datetime
        ";

        $statement = $this->database->prepare($query);
        $statement->bindParam(":startTimestamp", $startTimestamp);
        $statement->bindParam(":endTimestamp", $endTimestamp);
        $statement->execute();

        $collection = new Collection();

        /* Anyone in the EOTC permissions sytem can also see it
         * We don't need to iterate with this, since $user doesn't
         * actually change between iterations
         *
         * EOTC managers and superusers always see events
         */
        $isEOTCManager = false;

        $user = $this->getUser();
        if ($user->isSuperuser()) {
            $isEOTCManager = true;
        } else {
            $permissionMapper = new EventPermissionMapper();
            $allPermissions   = $permissionMapper->findByUser($user);

            if ($allPermissions->count()) {
                $isEOTCManager = true;
            }
        }

        foreach ($statement as $row) {
            /** @var Event $eventItem */
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

            /* Accumulate the metadata for this event
             * Only use the first record
             */
            $eventItem->setMetadata($this->findMetadata($eventItem->getId())->first());

            /* Determine current status
             */
            if ($eventItem->getMetadata()) {
                $metadataMapper = new EventMetadataMapper();
                $metadataMapper->loadJoinedModel('status', $eventItem->getMetadata());

                /* Skip over rejected events so they don't display in the calendar grid,
                 * obscuring your vision, occupying your real estate
                 */
                if ($eventItem->getMetadata()->getStatus()->isRejected()) {
                    continue;
                }

                /* Accumulate staff attendees for this event
                 */
                $allStaffAttendees = $metadataMapper->findStaffAttendees($eventItem->getMetadata()->getId());
                $eventItem->getMetadata()->setStaffAttendees($allStaffAttendees);

                /* Accumulate student attendees for this event
                 */
                $allStudentAttendees = $metadataMapper->findStudentAttendees($eventItem->getMetadata()->getId());
                $eventItem->getMetadata()->setStudentAttendees($allStudentAttendees);
            }

            /* For filters
             */
            $campusIds = array();

            foreach ($eventItem->getCampusCollection() as $currentCampus) {
                $campusIds[] = $currentCampus->getId();
            }

            /* Only present this event for a particular user if they are
             * superuser or attendee (teacher in charge, staff, student)
             * or are in the EOTC permissions system
             *
             * Save some hassle if you're already an EOTC manager, skip all
             * this other mapper stuff
             */
            if (!$isEOTCManager) {
                $isAttendee = false;

                /* Superusers can always see it
                 */
                if (!$isAttendee) {
                    if ($user->isSuperuser()) {
                        $isAttendee = true;
                    }
                }

                /* Creators can always see it
                 */
                if (!$isAttendee) {
                    if ($eventItem->getAuthor()->getId() == $user->getId()) {
                        $isAttendee = true;
                    }
                }

                /* Teachers in Charge should see it in translucent form
                 */
                if (!$isAttendee) {
                    $isAttendee = $user->getId() == $eventItem->getTeacherInCharge()->getId();
                }

                /* Staff members should see it in translucent form
                 */
                if (!$isAttendee) {
                    foreach ($eventItem->getMetadata()->getStaffAttendees() as $currentStaff) {
                        if ($currentStaff->getId() == $user->getId()) {
                            $isAttendee = true;
                            break;
                        }
                    }
                }

                /* Students should only see this event if it's Approved and
                 * if they are assigned to it as an attendee
                 */
                if (!$isAttendee) {
                    if ($eventItem->getMetadata()->getStatus()->isApproved()) {
                        foreach ($eventItem->getMetadata()->getStudentAttendees() as $currentStudent) {
                            if ($currentStudent->getId() == $user->getId()) {
                                $isAttendee = true;
                                break;
                            }
                        }
                    }
                }
            }

            if ($isEOTCManager || $isAttendee || $eventItem->getMetadata()->getStatus()->isApproved()) {
                /* Translate an \EOTC\Event type into a
                                 * Calendar_Event_Type_Abstract type
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
                $event->setTitle($eventItem->getTitle());
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
                $event->setCompleted(0);

                /* Custom data
                 */
                $event->setCustom(
                    array(
                        'approved' => $eventItem->getMetadata()->getStatus()->isApproved()
                    )
                );

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
