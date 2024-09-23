<?php

/**
 * Csv
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Import;

use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventType;
use Schoolbox\Calendar\Relation\Category;
use Schoolbox\Calendar\Relation\CategoryMapper;
use Schoolbox\Calendar\Relation\Role;
use Schoolbox\Calendar\Relation\RoleMapper;
use Schoolbox\CampusMapper;
use Alaress_Date_Format;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Realtime\Schedule\Trigger\Test\MockPropagator;
use Schoolbox\Resource\Folder;

class Csv extends Base
{
    /**
     * Load all events into schoolbox, generate outcome array
     */
    public function load(): void
    {
        $output = array();

        $campusMapper = new CampusMapper();
        $eventMapper = new EventMapper();
        $categoryRelationMapper = new CategoryMapper();
        $roleRelationMapper = new RoleMapper();
        $folderMapper = Folder\Mapper::getInstance();
        $folderCache  = [];

        // silence notifications
        $mockPropagator = new MockPropagator();

        $campusMapper->setEventPropagator($mockPropagator);
        $eventMapper->setEventPropagator($mockPropagator);
        $categoryRelationMapper->setEventPropagator($mockPropagator);
        $roleRelationMapper->setEventPropagator($mockPropagator);

        /* All the campuses selected in the ui
         */
        $allCampuses = [];

        foreach ($this->getCampusList() as $campusId) {
            $campus = $campusMapper->findById($campusId);
            $allCampuses[] = $campus;
        }

        /* Process rows
         */
        $handle = fopen($this->getFile(), 'r');
        $handle = (new \Alaress\Csv\ValidCSV($handle))->skipBom();
        $headerRow = fgetcsv($handle, 0, ",");

        $eventTypeMapper = new EventTypeMapper();

        while (($thisRow = fgetcsv($handle, 0, ",")) !== false) {
            // The CSV file will not contain character encoding info, so just assume a compatible encoding has been used.
            // Previously we stripped extra bytes from multi-byte characters here (to convert non-ASCII to ASCII),
            // but users complained about accented characters losing their accents.

            [
                $start_date,
                $start_time,
                $finish_date,
                $finish_time,
                $all_day,
                $name,
                $detail,
                $location,
                $event_type,
                $publish_date,
                $attendance,
                $folderIds,
            ] = array_pad($thisRow, 12, '');

            /* Event name is mandatory
             */
            if (!strlen(trim($name))) {
                $output[] = array(
                    'name'   => $name,
                    'status' => 'Not Added',
                    'error'  => 'Event name not provided',
                );

                continue;
            }

            /* Start date is mandatory
             */
            if (!strlen(trim($start_date))) {
                $output[] = array(
                    'name'   => $name,
                    'status' => 'Not Added',
                    'error'  => 'Start date not provided',
                );

                continue;
            }

            /* Skip empty comments and no dates
             *
             * If access is 3, then the sid corresponds to campus id's
             * If access is 2, then the sid corresponds to group id's
             */
            try {
                $start = new Alaress_Date_Format($start_date, 'd/m/Y');
                //
                // Finish empty? Use start date as the finish.
                if (!empty(strlen(trim($finish_date)))) {
                    $finish = new Alaress_Date_Format($finish_date, 'd/m/Y');
                } else {
                    $finish = clone $start;
                }
            } catch (\InvalidArgumentException $e) {
                $output[] = array(
                    'name'   => $name,
                    'status' => 'Not Added',
                    'error'  => 'Invalid date time supplied',
                );

                continue;
            }

            //
            // Start or finish time empty? Default to start and end of day.
            $start_time = strlen(trim($start_time)) ? $start_time : '00:00';
            $finish_time = strlen(trim($finish_time)) ? $finish_time : '23:59';

            //
            // All day? if "all day" flag is set.
            // (maybe if start_time = 00:00:00 and end_time = 23:59:59 too?)
            $all_day =
                in_array(trim(strtolower($all_day)), array('y', 'yes', '1'))
                || ($start_time === '00:00:00' && $finish_time === '23:59:59');

            /* Event Type
             * If one not found, report error and move on
             */
            try {
                /** @var EventType $eventType */
                $eventType = $eventTypeMapper->findById($event_type);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $output[] = array(
                    'name'   => $name,
                    'status' => 'Not Added',
                    'error'  => 'Event type not set',
                );

                continue;
            }

            /* 24 hour time enforcement
             * Check validity of event settings e.g. datetimes
             *
             * These should be put into Alaress_Date_Format, I think,
             * rather than floating around in here. But Boomerang's got
             * some stuff coming in fifteen so
             */

            /* Default value for all-day events
             */
            if (preg_match('#(00:00:00)|(23:59:59)#', $start_time)) {
                $startDatetimeString = "{$start_date} {$start_time}";
            } elseif (preg_match('#^((0?\d)|(1\d)|(2[0-3])):([0-5]\d)$#', $start_time)) {
                /* Prescribed 24-hour format from csv, append :00 seconds
             */
                $startDatetimeString = "{$start_date} {$start_time}:00";
            } else {
                $output[] = array(
                    'name'   => $name,
                    'status' => 'Not Added',
                    'error'  => 'Invalid start date and/or times (use 24-hour h:m format)',
                );

                continue;
            }

            try {
                $startDatetime = new Alaress_Date_Format($startDatetimeString, 'd/m/Y H:i:s');
            } catch (\InvalidArgumentException $e) {
                $output[] = [
                    'name'   => $name,
                    'status' => 'Not Added',
                    'error'  => 'Invalid start date time supplied',
                ];
                continue;
            }

            /* Default value for all-day events
             */
            if (preg_match('#(00:00:00)|(23:59:59)#', $finish_time)) {
                $finishDatetimeString = "{$finish_date} {$finish_time}";
            } elseif (preg_match('#^((0?\d)|(1\d)|(2[0-3])):([0-5]\d)$#', $finish_time)) {
                /* Prescribed 24-hour format from csv, append :00 seconds
             */
                $finishDatetimeString = "{$finish_date} {$finish_time}:00";
            } else {
                $output[] = array(
                    'name'   => $name,
                    'status' => 'Not Added',
                    'error'  => 'Invalid finish date and/or times (use 24-hour h:m format)',
                );

                continue;
            }

            try {
                $finishDatetime = new Alaress_Date_Format($finishDatetimeString, 'd/m/Y H:i:s');
            } catch (\InvalidArgumentException $e) {
                $output[] = [
                    'name'   => $name,
                    'status' => 'Not Added',
                    'error'  => 'Invalid finish date time supplied',
                ];
                continue;
            }

            /* Start datetime occurs after finish datetime
             */
            if ($startDatetime->getUnixTimeStamp() > $finishDatetime->getUnixTimeStamp()) {
                $output[] = array(
                    'name'   => $name,
                    'status' => 'Not Added',
                    'error'  => 'Invalid date and/or times',
                );

                continue;
            }

            /* Save
             */

            $event = new Event();
            $event->setOwner($this->getUser());
            $event->setAuthor($this->getAuthor());
            $event->setAccess($this->getAccess());
            $event->setStartDateTime($startDatetime);
            $event->setEndDateTime($finishDatetime);
            $event->setTitle($name);
            $event->setDetail($detail);
            $event->setLocation($location);
            $event->setAllDay($all_day);
            $event->setType($eventType);
            $event->setAttendance($attendance);
            $event->setCampusCollection($allCampuses);
            $event->setSource($this->getSource());

            /* If a publish date is specified, use it; otherwise leave as empty
             */
            if (strlen(trim($publish_date))) {
                try {
                    $publishDate = new Alaress_Date_Format($publish_date, 'd/m/Y');
                } catch (\InvalidArgumentException $e) {
                    $output[] = [
                        'name'   => $name,
                        'status' => 'Not Added',
                        'error'  => 'Invalid publish date supplied',
                    ];
                    continue;
                }
                $event->setPublishDateTime($publishDate);
            }

            // Allow override of folder relations only if no folder list has
            // been set in the object
            $folderList = $this->categoryList;
            if (empty($folderList)) {
                $folderList = [];
                foreach (explode(',', $folderIds) as $folderId) {
                    // Empty folder ID: just ignore
                    if ($folderId === '') {
                        continue;
                    }

                    // Is this really a folder ID?
                    $folderIdNum = filter_var($folderId, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                    if (!$folderIdNum) {
                        $output[] = [
                            'name'   => $name,
                            'status' => 'Not Added',
                            'error'  => sprintf('Folder ID "%s" is not a number', $folderId),
                        ];
                        // If not skip this entire CSV row
                        continue 2;
                    }

                    // Check the folder ID actually exists
                    try {
                        $folderCache[$folderIdNum] ??= $folderMapper->findById($folderIdNum);
                    } catch (MapperException) {
                        $output[] = [
                            'name'   => $name,
                            'status' => 'Not Added',
                            'error'  => sprintf('Folder ID %d not found', $folderIdNum),
                        ];
                        // If not skip this entire CSV row
                        continue 2;
                    }
                    $folderList[] = $folderIdNum;
                }
            }

            if (!empty($folderList)) {
                $event->setAccess(Event::ACCESS_GROUP);
            }

            /* Save it
             */
            $result = $eventMapper->save($event);
            if (is_array($result) && count($result)) {
                /* Assign Category relations:
                 * log what it was added to
                 */
                if ($event->isGroupLevel()) {
                    foreach ($folderList as $folderId) {
                        $category = new Category();
                        $category->setEventId($event->getId());
                        $category->setCategoryId($folderId);
                        $categoryRelationMapper->save($category);
                    }
                }

                /* Assign Role relations
                 */
                if ($event->isCampusLevel()) {
                    foreach ($this->getGroupList() as $currentRole) {
                        $role = new Role();
                        $role->setEventId($event->getId());
                        $role->setRoleId($currentRole);
                        $roleRelationMapper->save($role);
                    }
                }
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

            /* Free memory */
            unset($publish_date, $start, $finish);
        }

        $this->setOutput($output);
    }
}
