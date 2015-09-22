<?php
/**
 * Csv
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Calendar\Import;

use \Schoolbox\Calendar\Event;
use \Schoolbox\Calendar\EventMapper;
use \Schoolbox\Calendar\Relation\Category;
use \Schoolbox\Calendar\Relation\CategoryMapper;
use \Schoolbox\Calendar\Relation\Role;
use \Schoolbox\Calendar\Relation\RoleMapper;
use \Schoolbox\CampusMapper;
use \Alaress_Date_Format;
use Schoolbox\Notif\SilentNotif;
use \Schoolbox\Calendar\EventTypeMapper;
use Alaress\Lumberjack\Logger;

class Csv extends Base {

	/**
	 * Load all events into schoolbox, generate outcome array
	 */
	public function load() {
		$output = array();

		$campusMapper = new CampusMapper();
		$eventMapper = new EventMapper();
		$categoryRelationMapper = new CategoryMapper();
		$roleRelationMapper = new RoleMapper();

        // silence notifications
        $silence = new SilentNotif();

        $campusMapper->setNotifier($silence);
        $eventMapper->setNotifier($silence);
        $categoryRelationMapper->setNotifier($silence);
        $roleRelationMapper->setNotifier($silence);

		/* All the campuses selected in the ui
		 */
		$allCampuses = $campusMapper->findByColumn('id', $this->getCampusList());

		/* Process rows
		 */
		$handle = fopen($this->getFile(), 'r');
		$headerRow = fgetcsv($handle, 0, ",");

		while (($thisRow = fgetcsv($handle, 0, ",")) !== false) {
			//
			// We cannot determine encoding, so strip everything not ascii
			$thisRow = array_map(
				function($field) { return preg_replace('/[\\x80-\\xFF]/', '', $field); },
				$thisRow
			);
			list(
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
				$attendance
				) = $thisRow;

			/* Skip empty comments and no dates
			 *
			 * If access is 3, then the sid corresponds to campus id's
			 * If access is 2, then the sid corresponds to group id's
			 */
			$start = new Alaress_Date_Format($start_date, 'd/m/Y');
			//
			// Finish empty? Use start date as the finish.
			$finish = (strlen(trim($finish_date))) ?
				new Alaress_Date_Format($finish_date, 'd/m/Y'):
				clone $start;

			//
			// Start or finish time empty? Default to start and end of day.
			$start_time  = strlen(trim($start_time))  ? $start_time  : '00:00';
			$finish_time = strlen(trim($finish_time)) ? $finish_time : '23:59';

			//
			// All day? if "all day" flag is set.
			// (maybe if start_time = 00:00:00 and end_time = 23:59:59 too?)
			$all_day =
				in_array(trim(strtolower($all_day)), array('y', 'yes', '1'))
				|| ($start_time === '00:00:00' && $finish_time === '23:59:59')
			;

			/* Event Type
			 * If one not found, report error and move on
			 */
			try {
				$eventTypeMapper = new EventTypeMapper();
				$eventType = $eventTypeMapper->findById($event_type);
			}
			catch (\Exception $e) {
				$output[] = array(
					'name' => \Scrub::text($name),
					'status' => 'Not Added',
					'error' => 'Event type not set',
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
                $startDatetime = new \Alaress_Date_Format("{$start_date} {$start_time}", 'd/m/Y H:i:s');
            }
            /* Prescribed 24-hour format from csv, append :00 seconds
             */
            elseif (preg_match('#^((0?\d)|(1\d)|(2[0-3])):([0-5]\d)$#', $start_time)) {
                $startDatetime = new \Alaress_Date_Format("{$start_date} {$start_time}:00", 'd/m/Y H:i:s');
            }
            else {
                $output[] = array(
                    'name' => \Scrub::text($name),
                    'status' => 'Not Added',
                    'error' => 'Invalid start date and/or times (use 24-hour h:m format)',
                );

                continue;
            }

            /* Default value for all-day events
             */
            if (preg_match('#(00:00:00)|(23:59:59)#', $finish_time)) {
                $finishDatetime = new \Alaress_Date_Format("{$finish_date} {$finish_time}", 'd/m/Y H:i:s');
            }
            /* Prescribed 24-hour format from csv, append :00 seconds
             */
            elseif (preg_match('#^((0?\d)|(1\d)|(2[0-3])):([0-5]\d)$#', $finish_time)) {
                $finishDatetime = new \Alaress_Date_Format("{$finish_date} {$finish_time}:00", 'd/m/Y H:i:s');
            }
            else {
                $output[] = array(
                    'name' => \Scrub::text($name),
                    'status' => 'Not Added',
                    'error' => 'Invalid finish date and/or times (use 24-hour h:m format)',
                );

                continue;
            }
            
            /* Start datetime occurs after finish datetime
             */
            if ($startDatetime->getUnixTimestamp() > $finishDatetime->getUnixTimestamp()) {
                $output[] = array(
                    'name' => \Scrub::text($name),
                    'status' => 'Not Added',
                    'error' => 'Invalid date and/or times',
                );

                continue;
            }

			/* Save
			 */
			if ((trim($thisRow[0]) != '') && (trim($name) != '')) {
				$event = new Event();
				$event->setOwner($this->getUser());
				$event->setAuthor($this->getAuthor());
				$event->setAccess($this->getAccess());
				$event->setStartDateTime($startDatetime);
				$event->setEndDateTime($finishDatetime);
				$event->setTitle($name);
				$event->setDetail(nl2br(\Scrub::text($detail)));
				$event->setLocation($location);
				$event->setAllDay($all_day);
				$event->setType($event_type);
				$event->setAttendance($attendance);
				$event->setCampusCollection($allCampuses);

				/* If a publish date is specified, use it; otherwise leave as empty
				 */
				if (strlen(trim($publish_date))) {
					$pdate = new Alaress_Date_Format($publish_date, 'd/m/Y');
					$event->setPublishDateTime($pdate);
				}

				/* Save it
				 */
				$result = $eventMapper->save($event);

				/* Assign Category relations
				 */
				foreach($this->getCategoryList() as $currentCategory) {
					$category = new Category();
					$category->setEventId($event->getId());
					$category->setCategoryId($currentCategory);
					$categoryRelationMapper->save($category);
				}

				/* Assign Role relations
				 */
				foreach($this->getGroupList() as $currentRole) {
					$role = new Role();
					$role->setEventId($event->getId());
					$role->setRoleId($currentRole);
					$roleRelationMapper->save($role);
				}

				if (is_array($result) && count($result)) {
					$output[] = array(
						'name' => \Scrub::text($event->getTitle()),
						'status' => 'Added',
						'error' => 'OK',
					);
				}
				else {
					$output[] = array(
						'name' => \Scrub::text($event->getTitle()),
						'status' => 'Not Added',
						'error' => 'Could not create event',
					);
				}
			}
			else {
				$output[] = array(
					'name' => \Scrub::text($name),
					'status' => 'Not Added',
					'error' => 'Missing date and/or name',
				);
			}

			/* Free memory */
			unset($publish_date, $start, $finish);
		}

		$this->setOutput($output);
	}
}