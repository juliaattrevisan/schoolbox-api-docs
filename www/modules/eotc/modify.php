<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* Modify existing event
 */
if (is_numeric($this->arg[0])) {
	$mapper = new \EOTC\Calendar\EventMapper();
	$event = $mapper->findById($this->arg[0]);

	new \Alaress_Probe($event);
}
/* Create new event
 */
else {
	$this->setFile(TEMPLATE_content, 'modules/eotc/modify.html');

	/* Miscellaneous setvarring
	 */
	$this->setVar(array(
		'FUNCTION' => 'Add',
	));

	$this->setVar(array(
		'EVENT_ID' => '',
		'EVENT_USER_ID' => $_SESSION['user']->getId(),
		'EVENT_AUTHOR_ID' => $_SESSION['user']->getId(),
		'EVENT_NOTE' => '',
		'EVENT_START_DATE' => '',
		'EVENT_START_HOUR' => '',
		'EVENT_START_MIN' => '',
		'EVENT_START_AM' => '',
		'EVENT_START_PM' => '',
		'EVENT_END_DATE' => '',
		'EVENT_END_HOUR' => '',
		'EVENT_END_MIN' => '',
		'EVENT_END_AM' => '',
		'EVENT_END_PM' => '',
		'EVENT_ALLDAY_CHECKED' => '',
		'EVENT_PUBLISH_DATE' => '',
		'EVENT_LOCATION' => '',
	));

	/* Hide advanced options by default
	 */
	$this->setVar(array(
		'SHOW_ADVANCED' => '',
		'ADVANCED_SHOW' => '',
		'ADVANCED_CLOSE' => 'hidden',
	));

	/* Event Types
	 */
	$calendarEventTypeMapper = new \EOTC\Calendar\EventTypeMapper();
	$allEventTypes = $calendarEventTypeMapper->findAll();

	$this->dropDown('type_drop', $allEventTypes, '', TEMPLATE_content, array(
		'id' => 'EVENT_EVENTTYPE_ID',
		'type' => 'EVENT_EVENTTYPE_NAME'
	));

	/* Only provide Group option if groups have been defined
	 */
	$groupHandler = new \Group();
	$groupList = $groupHandler->involvedInCategoryList($_SESSION['user']->getId(), true, WRITE_ACCESS);

	/* Only provide Campus options if campuses have been defined
	 */
	$calendarHandler = new \Calendar();
	$campusList = $calendarHandler->getCampusList();

	$campusMapper = new \Schoolbox\CampusMapper();
	$campusList = $campusMapper->getAvailableCampusesForUser($_SESSION['user']);

	/* Render all the dropdowns
	 */

	/* Event Access Levels
	 * Requires both $groupList and $campusList for optional values
	 */
	$this->dropDown('event_level_drop', new \EOTC\Calendar\EventLevelCollection($groupList, $campusList), '', TEMPLATE_content, array(
		'id' => 'EVENT_EVENTLEVEL_ID',
		'name' => 'EVENT_EVENTLEVEL_NAME',
	));

	/* Group dropdown when using Group Event Level
	 */
	$this->dropDown('event_group_drop', $groupList, $_GET['sid'], TEMPLATE_content, array(
		'id' => 'EVENT_GROUP_ID',
		'name' => 'EVENT_GROUP_NAME'
	));

	/* Campus Checkbox Array when using Campus Event Level
	 */
	if ($campusList->count()) {
		foreach($campusList as $current) {
			$allCampuses[] = array(
				'id' => $current->getId(),
				'name' => $current->getName()
			);
		}

		$this->checkList('event_campus_list', $allCampuses, array($_SESSION['campus'][0]), TEMPLATE_content, array(
			'id' => 'EVENT_CAMPUS_ID',
			'name' => 'EVENT_CAMPUS_NAME',
		));
	}
	else {
		$this->setBlock(TEMPLATE_content, 'event_campus_list', 'event_campus_list_block');
		$this->setVar('event_campus_list_block', '');
	}

	/* Roles, when Campus Event Level is used
	 */
	$roleMapper = new \Schoolbox\RoleMapper();
	$roleList = $roleMapper->findAll();

	/* Roles checkbox array when using Campus Event Level
	 * This really needs to be updated - why is checkList behaviour different to
	 * the dropDown one when it comes to collections?
	 */
	foreach($roleList as $current) {
		$allRoles[] = array(
			'id' => $current->getId(),
			'name' => $current->getName()
		);
	}

	$this->checkList('event_role_list', $allRoles, '', TEMPLATE_content, array(
		'id' => 'EVENT_ROLE_ID',
		'name' => 'EVENT_ROLE_NAME'
	));
}