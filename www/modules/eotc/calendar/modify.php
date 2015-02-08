<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* Direct access to modify.php -_-
 */
require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');
$page = new SitePage();

$page->setFile(TEMPLATE_content, 'modules/eotc/modify.html');
$page->setBlock(TEMPLATE_content, 'recurring_dates', 'recurring_dates_block');
$page->setBlock(TEMPLATE_content, 'recurring_reminder', 'recurring_reminder_block');

/* Modify existing event
 */
if (is_numeric($_GET['eventID'])) {
	$mapper = new \Schoolbox\Calendar\EventMapper();

	try {
		$event = $mapper->findById($_GET['eventID']);
	}
	catch (\Exception $e) {
		error(160);
		exit;
	}

	/* Default values
	 */
	$defaultTypeId = $event->getType()->getId();
	$defaultSid = $event->getServiceId();
	$defaultAccessLevel = $event->getAccess();

	/* Only provide publish date if one was provided and if it
	 * wasn't useless somehow
	 */
	if ($event->getPublishDateTime() && strlen($event->getPublishDateTime()->getDatabase())) {
		$publishDate = $event->getPublishDateTime()->getLocalShort();
	}
	else {
		$publishDate = '';
	}

	/* Miscellaneous setvarring
	 * Hide advanced options by default
	 */
	$page->setVar(array(
		'FUNCTION' => 'Update',
		'SHOW_ADVANCED' => '',
		'ADVANCED_SHOW' => '',
		'ADVANCED_CLOSE' => 'hidden',
	));

	$page->setVar(array(
		'EVENT_ID' => $event->getId(),
		'EVENT_USER_ID' => $_SESSION['user']->getId(),
		'EVENT_AUTHOR_ID' => $_SESSION['user']->getId(),
		'EVENT_TITLE' => $event->getTitle(),
		'EVENT_ALLDAY_CHECKED' => $event->getAllDay() ? 'checked="checked"' : '',
		'EVENT_TIMEINPUTS' => $event->getAllDay() ? 'hide' : '',
		'EVENT_START_DATE' => $event->getStartDateTime()->getUsingFormat('d/m/Y'),
		'EVENT_START_HOUR' => $event->getStartDateTime()->getUsingFormat('H'),
		'EVENT_START_MIN' => $event->getStartDateTime()->getUsingFormat('i'),
		'EVENT_START_AM' => $event->getStartDateTime()->getUsingFormat('A') == 'AM' ? 'selected="selected"' : '',
		'EVENT_START_PM' => $event->getStartDateTime()->getUsingFormat('A') == 'PM' ? 'selected="selected"' : '',
		'EVENT_END_DATE' => $event->getEndDateTime()->getUsingFormat('d/m/Y'),
		'EVENT_END_HOUR' => $event->getEndDateTime()->getUsingFormat('H'),
		'EVENT_END_MIN' => $event->getEndDateTime()->getUsingFormat('i'),
		'EVENT_END_AM' => $event->getEndDateTime()->getUsingFormat('A') == 'AM' ? 'selected="selected"' : '',
		'EVENT_END_PM' => $event->getEndDateTime()->getUsingFormat('A') == 'PM' ? 'selected="selected"' : '',
		'EVENT_PUBLISH_DATE' => $publishDate,
		'EVENT_LOCATION' => $event->getLocation(),
		'EVENT_DETAIL' => $event->getDetail(),
		'EVENT_REDIRECT' => $event->setupRedirect($_GET['returnTo'], $event->getStartDateTime()->getUsingFormat('d-m-Y'), $_GET['sid'], $_GET['userId']),
	));

	/* Recurrence
	 */
	// Is there a recurrence? If so don't show the repeat selector.
	$recurrence = array();
	if ($event->getSeriesId()) {
		$eventRecurrenceMapper = new \Schoolbox\Calendar\EventRecurrenceMapper($mapper);
		$recurrence            = $eventRecurrenceMapper->getSeriesRecurrence($event);
	}
	if (!empty($recurrence)) {
		$page->setVar('EVENT_REC_FREQ', ucfirst($recurrence['freq']));
		$page->setVar('EVENT_REC_START', $recurrence['start']->getLongDateOnly());
		$page->setVar('EVENT_REC_END', $recurrence['end']->getLongDateOnly());
		$page->parse('recurring_reminder_block', 'recurring_reminder');
		$page->setVar('recurring_dates_block', '');
	} else {
		$page->setVar('recurring_reminder_block', '');
		$page->parse('recurring_dates_block', 'recurring_dates');
	}
}
/* Create new event
 */
else {

	/* Set up default dates
	 */
	$event = new \Schoolbox\Calendar\Event();
	$event->setupDates($_GET['date']);

	$defaultTypeId = '';
	$defaultSid = '';
	$defaultAccessLevel = '';

	/* Miscellaneous setvarring
	 * Hide advanced options by default
	 */
	$page->setVar(array(
		'FUNCTION' => 'Add',
		'SHOW_ADVANCED' => '',
		'ADVANCED_SHOW' => '',
		'ADVANCED_CLOSE' => 'hidden',
	));

	$page->setVar(array(
		'EVENT_ID' => '',
		'EVENT_USER_ID' => $_SESSION['user']->getId(),
		'EVENT_AUTHOR_ID' => $_SESSION['user']->getId(),
		'EVENT_TITLE' => '',
		'EVENT_ALLDAY_CHECKED' => 'checked="checked"',
		'EVENT_TIMEINPUTS' => 'hide',
		'EVENT_START_DATE' => $event->getStartDateTime()->getUsingFormat('d/m/Y'),
		'EVENT_START_HOUR' => '',
		'EVENT_START_MIN' => '',
		'EVENT_START_AM' => '',
		'EVENT_START_PM' => '',
		'EVENT_END_DATE' => $event->getEndDateTime()->getUsingFormat('d/m/Y'),
		'EVENT_END_HOUR' => '',
		'EVENT_END_MIN' => '',
		'EVENT_END_AM' => '',
		'EVENT_END_PM' => '',
		'EVENT_PUBLISH_DATE' => '',
		'EVENT_LOCATION' => '',
		'EVENT_DETAIL' => '',
		'EVENT_REDIRECT' => $event->setupRedirect($_GET['returnTo'], $event->getStartDateTime()->getUsingFormat('d-m-Y'), $_GET['sid'], $_GET['userId']),
	));

	/* Recurrence
	 */
	$page->setVar(array(
		'recurring_reminder_block' => '',
	));

	$page->parse('recurring_dates_block', 'recurring_dates');
}

/* Event Types
 */
$calendarEventTypeMapper = new \Schoolbox\Calendar\EventTypeMapper();
$allEventTypes = $calendarEventTypeMapper->findAll();

$page->dropDown('type_drop', $allEventTypes, $defaultTypeId, TEMPLATE_content, array(
	'id' => 'EVENT_EVENTTYPE_ID',
	'type' => 'EVENT_EVENTTYPE_NAME'
));

/* Only provide Group option if groups have been defined
 */
$groupHandler = new \Group();
$groupList = $groupHandler->involvedInCategoryList($_SESSION['user']->getId(), true, WRITE_ACCESS);

/* Only provide Campus options if campuses have been defined
 */
$campusMapper = new \Schoolbox\CampusMapper();
$campusList = $campusMapper->getAvailableCampusesForUser($_SESSION['user']);

/* Render all the dropdowns
 */

/* Event Access Levels
 * Requires both $groupList and $campusList for optional values
 */
$page->dropDown('event_level_drop', new \Schoolbox\Calendar\EventLevelCollection($groupList, $campusList), $defaultAccessLevel, TEMPLATE_content, array(
	'id' => 'EVENT_EVENTLEVEL_ID',
	'name' => 'EVENT_EVENTLEVEL_NAME',
));

/* Group dropdown when using Group Event Level
 */
$page->dropDown('event_group_drop', $groupList, $defaultSid, TEMPLATE_content, array(
	'id' => 'EVENT_GROUP_ID',
	'name' => 'EVENT_GROUP_NAME'
));

/* Campus Checkbox Array when using Campus Event Level
 */
if ($campusList->count()) {
	$allCampuses = array();

	foreach($campusList as $current) {
		$allCampuses[] = array(
			'id' => $current->getId(),
			'name' => $current->getName()
		);
	}

	$page->checkList('event_campus_list', $allCampuses, array($_SESSION['campus'][0]), TEMPLATE_content, array(
		'id' => 'EVENT_CAMPUS_ID',
		'name' => 'EVENT_CAMPUS_NAME',
	));
}
else {
	$page->setBlock(TEMPLATE_content, 'event_campus_list', 'event_campus_list_block');
	$page->setVar('event_campus_list_block', '');
}

/* Roles, when Campus Event Level is used
 */
$roleMapper = new \Schoolbox\RoleMapper();
$roleList = $roleMapper->findAll();

/* Roles checkbox array when using Campus Event Level
 * This really needs to be updated - why is checkList behaviour different to
 * the dropDown one when it comes to collections?
 */
$allRoles = array();

foreach($roleList as $current) {
	$allRoles[] = array(
		'id' => $current->getId(),
		'name' => $current->getName()
	);
}

$page->checkList('event_role_list', $allRoles, $defaultSid, TEMPLATE_content, array(
	'id' => 'EVENT_ROLE_ID',
	'name' => 'EVENT_ROLE_NAME'
));

$page->outputPage();

