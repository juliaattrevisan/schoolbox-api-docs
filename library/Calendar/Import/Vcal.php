<?php

/**
 * Vcal
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Import;

use Sabre\VObject;
use Sabre\VObject\Component\VEvent;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\Relation\Category;
use Schoolbox\Calendar\Relation\CategoryMapper;
use Schoolbox\Calendar\Relation\Role;
use Schoolbox\Calendar\Relation\RoleMapper;
use Schoolbox\CampusMapper;
use Alaress_Date_Format;
use Schoolbox\Realtime\Schedule\Trigger\Test\MockPropagator;

class Vcal extends Base
{
    /**
     * Find an event type from an array of possibilities
     * The array contains the "type" (name) values
     *
     * @param array $array
     * @return mixed
     */
    private function findEventTypeFromArray($array)
    {
        $allElements = array_map('trim', $array);
        $allElements = array_map('strtoupper', $allElements);
        $allElements = array_filter($allElements, 'strlen');

        /* Default meta details, just pick the first event type
         */
        $selectedEventType = $this->getAllEventTypes()->first();

        foreach ($allElements as $currentElement) {
            foreach ($this->getAllEventTypes() as $currentEventType) {
                if (strtoupper($currentEventType->getType()) === $currentElement) {
                    $selectedEventType = $currentEventType;
                    break;
                }
            }
        }

        return $selectedEventType;
    }

    /**
     * Load all events into schoolbox, generate outcome array
     *
     * @param bool $perform should it be a dry-run instead, this should always be true as this function
     *                      does not use the perform mode
     * @return void
     *
     * @throws VObject\InvalidDataException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function load(bool $perform = true): void
    {
        $output = [];

        if (is_file($this->getFile())) {
            $campusMapper = new CampusMapper();
            $eventMapper = new EventMapper();
            $categoryRelationMapper = new CategoryMapper();
            $roleRelationMapper = new RoleMapper();

            // silence notifications
            $mockPropagator = new MockPropagator();

            $campusMapper->setEventPropagator($mockPropagator);
            $eventMapper->setEventPropagator($mockPropagator);
            $categoryRelationMapper->setEventPropagator($mockPropagator);
            $roleRelationMapper->setEventPropagator($mockPropagator);

            /* All the campuses selected in the ui
             */
            $allCampuses = $campusMapper->findByColumn('id', $this->getCampusList());

            /* All the events from the file
             */
            $now = (new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'))->startOfMonth();
            $later = new \Alaress_Date_Format(strtotime('+1 year', $_SERVER['REQUEST_TIME']), 'U');

            /** @var VObject\Component\VCalendar $allEvents */
            $allEvents = VObject\Reader::read(file_get_contents($this->getFile()));

            /* Specifically look for events that are in the past, or are too far in the future
             * (this latter idea is probably less significant in the general usage) since events
             * in the past will be quietly disregarded by $allEvents->expand(). There's a check
             * inside the vendor library to ensure events start (or end) within the time range
             * [$now - $later].
             */
            $rangeStart = new \DateTime($now->getDatabaseDate());
            $rangeEnd = new \DateTime($later->getDatabaseDate());

            foreach ($allEvents->getComponents() as $currentEvent) {
                /* Skip non-VEvent instances
                 */
                if (!$currentEvent instanceof VEvent) {
                    continue;
                }

                if (!$currentEvent->isInTimeRange($rangeStart, $rangeEnd)) {
                    $output[] = [
                        'name'   => \Scrub::text((string)$currentEvent->SUMMARY),
                        'status' => 'Not Added',
                        'error'  => 'Event must start or end between now and one year later',
                    ];
                }
            }

            /* Forcibly expand the date range requirements of every event, silently excluding those
             * which somehow fall outside this date range.
             */
            $allEvents = $allEvents->expand($rangeStart, $rangeEnd);

            foreach ($allEvents->getComponents() as $currentEvent) {
                /* Skip non-VEvent instances
                 */
                if (!$currentEvent instanceof VEvent) {
                    continue;
                }

                /* Event name is too long
                 */
                if (strlen(trim((string)$currentEvent->SUMMARY)) > self::MAX_EVENT_TITLE) {
                    $output[] = [
                        'name'   => \Scrub::text((string)$currentEvent->SUMMARY),
                        'status' => 'Not Added',
                        'error'  => 'Event name is too long',
                    ];

                    continue;
                }

                /* Skip cancelled event instances
                 * Log them to the user then carry on
                 */
                if (\Calendar_Api_EventType_Event_Vcal_Default::STATUS_CANCELLED === (string)$currentEvent->STATUS) {
                    $output[] = [
                        'name'   => \Scrub::text((string)$currentEvent->SUMMARY),
                        'status' => 'Not Added',
                        'error'  => 'Event was cancelled',
                    ];

                    continue;
                }

                /* Set up some aliases
                 */
                $eventStartTimestamp = strtotime((string)$currentEvent->DTSTART);
                $eventEndTimestamp = strtotime((string)$currentEvent->DTEND);

                /* Start datetime occurs after finish datetime
                 */
                if ($eventStartTimestamp > $eventEndTimestamp) {
                    $output[] = array(
                        'name'   => \Scrub::text((string)$currentEvent->SUMMARY),
                        'status' => 'Not Added',
                        'error'  => 'Invalid date and/or times',
                    );

                    continue;
                }

                // According to VCAL standard: end of all day event is 12am next day
                $allDay = !$currentEvent->DTSTART->hasTime();

                if ($allDay) {
                    --$eventEndTimestamp;
                }

                /* Determine some metadata about the event
                 */
                // Get category list for this event.
                // If this event has a category which matches
                // any event type, then apply that event type's
                // color instead of the configured one.
                $categoryList = explode(';', (string)$currentEvent->CATEGORIES);

                /* Put it all together
                 */
                $event = new Event();
                $event->setOwner($this->getUser());
                $event->setAuthor($this->getAuthor());
                $event->setStartDateTime(new Alaress_Date_Format($eventStartTimestamp, 'U'));
                $event->setEndDateTime(new Alaress_Date_Format($eventEndTimestamp, 'U'));
                $event->setTitle((string)$currentEvent->SUMMARY);
                $event->setAccess($this->getAccess());
                $event->setType($this->findEventTypeFromArray($categoryList));
                $event->setCampusCollection($allCampuses);
                $event->setDetail(nl2br((string)$currentEvent->DESCRIPTION));
                $event->setLocation((string)$currentEvent->LOCATION);
                $event->setAllDay($allDay);
                $event->setAttendance(0);
                $event->setCompleted(0);

                $isSuccessful = false;
                if ($perform) {
                    /* Save it
                     */
                    $result = $eventMapper->save($event);

                    /* Assign Category relations
                     */
                    foreach ($this->getCategoryList() as $currentCategory) {
                        $category = new Category();
                        $category->setEventId($event->getId());
                        $category->setCategoryId($currentCategory);
                        $categoryRelationMapper->save($category);
                    }

                    /* Assign Role relations
                     */
                    foreach ($this->getGroupList() as $currentRole) {
                        $role = new Role();
                        $role->setEventId($event->getId());
                        $role->setRoleId($currentRole);
                        $roleRelationMapper->save($role);
                    }

                    if (\is_array($result) && \count($result)) {
                        $isSuccessful = true;
                    }
                }
                /* Output messages
                 */
                if ($isSuccessful || !$perform) {
                    $output[] = array(
                        'name'   => $event->getTitle(),
                        'status' => 'Added',
                        'error'  => 'OK',
                    );
                } else {
                    $output[] = array(
                        'name'   => $event->getTitle(),
                        'status' => 'Not Added',
                        'error'  => 'Could not create event',
                    );
                }
            }
        }

        $this->setOutput($output);
    }
}
