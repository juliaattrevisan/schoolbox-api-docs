<?php
/**
 * Vcal
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Calendar\Import;

use \Sabre\VObject;
use \Sabre\VObject\Component\VEvent;
use \Schoolbox\Calendar\Event;
use \Schoolbox\Calendar\EventMapper;
use \Schoolbox\Calendar\Relation\Category;
use \Schoolbox\Calendar\Relation\CategoryMapper;
use \Schoolbox\Calendar\Relation\Role;
use \Schoolbox\Calendar\Relation\RoleMapper;
use \Schoolbox\CampusMapper;
use \Alaress_Date_Format;
use Schoolbox\Notif\SilentNotif;

class Vcal extends Base {

	/**
	 * Find an event type from an array of possibilities
	 * The array contains the "type" (name) values
	 *
	 * @param $array
	 * @return mixed
	 */
	private function findEventTypeFromArray($array) {
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
	 */
	public function load() {
		$output = array();

		if (is_file($this->getFile())) {
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

			/* All the events from the file
			 */
			$now = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');
			$later = new \Alaress_Date_Format(strtotime('+1 year', $_SERVER['REQUEST_TIME']), 'U');

			$allEvents = VObject\Reader::read(file_get_contents($this->getFile()));
			$allEvents->expand(new \DateTime($now->getDatabaseDate()), new \DateTime($later->getDatabaseDate()));

			foreach ($allEvents->children as $currentEvent) {
				if ($currentEvent instanceof VEvent) {

					/* Set up some aliases
					 */
					$eventStartTimestamp = strtotime((string)$currentEvent->DTSTART);
					$eventEndTimestamp = strtotime((string)$currentEvent->DTEND);

					// According to VCAL standard: end of all day event is 12am next day
					$allDay = !$currentEvent->DTSTART->hasTime();

					if ($allDay) {
						$eventEndTimestamp -= 1;
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
					$event->setAllday($allDay);
					$event->setAttendance(0);
					$event->setCompleted(0);

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

					/* Output messages
					 */
					if (is_array($result) && count($result)) {
						$output[] = array(
							'name' => $event->getTitle(),
							'status' => 'Added',
							'error' => 'OK',
						);
					}
					else {
						$output[] = array(
							'name' => $event->getTitle(),
							'status' => 'Not Added',
							'error' => 'Could not create event',
						);
					}
				}
			}
        }

		$this->setOutput($output);
	}
}