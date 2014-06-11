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
require_once(dirname(__FILE__) . '/../../../../include/Bootstrap.php');
$page = new SitePage();

$authenticator = new \EOTC\Authenticator();

if ($authenticator->isEOTCCreator($_SESSION['user'])) {
	$page->setFile(TEMPLATE_content, 'modules/eotc/manage/modify.html');
	$page->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');
	$page->setBlock(TEMPLATE_content, 'recurring_dates', 'recurring_dates_block');
	$page->setBlock(TEMPLATE_content, 'recurring_reminder', 'recurring_reminder_block');
	$page->setBlock(TEMPLATE_content, 'has_activity_log', 'has_activity_log_block');
	$page->setBlock('has_activity_log', 'activity_log', 'activity_log_block');
	$page->setBlock(TEMPLATE_content, 'can_add_students', 'can_add_students_block');
	$page->setBlock(TEMPLATE_content, 'can_add_resources', 'can_add_resources_block');

	/* Modify existing event
	 */
	if (is_numeric($_GET['eventID'])) {
		$mapper = new \EOTC\EventMapper();

		try {
			/** @var EOTC\Event $event */
			$event = $mapper->findById($_GET['eventID']);
		}
		catch (\Exception $e) {
			error(160);
		}

		/* Do a check for missing metadata
		 */
		if (!$event->getMetadata()) {
			throw new \Exception('No metadata record found for this event.');
		}

		/* Default values
		 */
		$defaultTypeId = $event->getType()->getId();
		$defaultCampusId = array();

		$allCampuses = $event->getCampusCollection()->getAll();

		foreach ($allCampuses as $currentCampus) {
			$defaultCampusId[] = $currentCampus->getId();
		}

		/* Default magicsuggest values
		 */
		$staffSelected = array();

		foreach ($event->getMetadata()->getStaffAttendees() as $currentStaff) {
			$staffSelected[] = array(
				'id' => $currentStaff->getId(),
				'name' => $currentStaff->getFullname()
			);
		}

		/* Teacher in charge
		 */
		if ($event->getTeacherInCharge()->getId()) {
			$teacherInCharge = array(
				'id' => $event->getTeacherInCharge()->getId(),
				'name' => $event->getTeacherInCharge()->getFullname(),
			);
		}
		else {
			$teacherInCharge = array();
		}

		/* Default magicsuggest values
		 */
		$resourceBookingSelected = array();

		foreach ($event->getMetadata()->getResourceBookings() as $currentResourceBooking) {
			$resourceBookingSelected[] = array(
				'id' => $currentResourceBooking->getId(),
				'name' => $currentResourceBooking->getName()
			);
		}

		/* Miscellaneous setvarring
		 * Hide advanced options by default
		 */
		$page->setVar(array(
			'FUNCTION' => 'Update',
			'EOTC_FUNCTION' => 'Update Event',
			'SHOW_ADVANCED' => '',
			'ADVANCED_SHOW' => '',
			'ADVANCED_CLOSE' => 'hidden',
		));

		if ($event->getMetadata()->getRamsRequired()) {
			if ($event->getMetadata()->getRamsFiles()->first()) {
				$filename = $event->getMetadata()->getRamsFiles()->first()->getFilename();
			}
			else {
				$filename = '';
			}
		}

		/* -_-
		 */
		$groupHandler = new Group();

		$page->setVar(array(
			'EVENT_RAMS_FILE' => $filename,
			'EVENT_CURRENT_STUDENTS' => $event->getMetadata()->getStudentAttendees()->count(),
			'EVENT_TEACHER_IN_CHARGE' => json_encode($teacherInCharge),
			'EVENT_RESOURCEBOOKING' => json_encode($resourceBookingSelected),
			'EVENT_STAFF' => json_encode($staffSelected),
			'EVENT_STATUS' => $event->getMetadata()->getStatus()->getName(),
			'EVENT_STATUS_CLASS' => $event->getMetadata()->getStatus()->getStyle(),
			'EVENT_RAMSREQUIRED_SELECTED' => $event->getMetadata()->getRamsRequired() ? 'checked="checked"' : '',
			'EVENT_RAMS_UPLOADED' => $event->getMetadata()->getRamsFiles()->count() ? 1 : 0,
			'EVENT_ID' => $event->getId(),
			'EVENT_AUTHOR_ID' => $event->getAuthor()->getId(),
			'EVENT_NOTE' => $event->getNote(),
			'EVENT_TIMEINPUTS' => $event->getAllDay() ? 'hide' : '',
			'EVENT_START_DATE' => $event->getStartDateTime()->getUsingFormat('d/m/Y'),
			'EVENT_START_HOUR' => $event->getStartDateTime()->getUsingFormat('g'),
			'EVENT_START_MIN' => $event->getStartDateTime()->getUsingFormat('i'),
			'EVENT_START_AM' => $event->getStartDateTime()->getUsingFormat('A') == 'AM' ? 'selected="selected"' : '',
			'EVENT_START_PM' => $event->getStartDateTime()->getUsingFormat('A') == 'PM' ? 'selected="selected"' : '',
			'EVENT_END_DATE' => $event->getEndDateTime()->getUsingFormat('d/m/Y'),
			'EVENT_END_HOUR' => $event->getEndDateTime()->getUsingFormat('g'),
			'EVENT_END_MIN' => $event->getEndDateTime()->getUsingFormat('i'),
			'EVENT_END_AM' => $event->getEndDateTime()->getUsingFormat('A') == 'AM' ? 'selected="selected"' : '',
			'EVENT_END_PM' => $event->getEndDateTime()->getUsingFormat('A') == 'PM' ? 'selected="selected"' : '',
			'EVENT_ALLDAY_CHECKED' => $event->getAllDay() ? 'checked="checked"' : '',
			'EVENT_STATUS_CLASS' => $event->getMetadata()->getStatus()->getStyle(),
			'EVENT_LOCATION' => $event->getLocation(),
			'EVENT_DETAIL' => $event->getDetail(),
			'EVENT_REDIRECT' => $event->setupRedirect($_GET['returnTo'], $event->getStartDateTime()->getUsingFormat('d-m-Y'), $_GET['sid'], $_GET['userId']),
			'STUDENT_GROUP_TYPE_IDS' => implode(';', $groupHandler->getStudentGroupTypeIds()),
			'STAFF_GROUP_TYPE_IDS' =>  implode(';', $groupHandler->getStaffGroupTypeIds()),
		));

		/* Recurrence
		 */
		// Is there a recurrence? If so don't show the repeat selector.
		if ($event->getSeriesId() == null) {
			$page->setVar('recurring_reminder_block', '');
			$page->parse('recurring_dates_block', 'recurring_dates');
		}
		else {
			$eventRecurrenceMapper = new \Schoolbox\Calendar\EventRecurrenceMapper($mapper);
			$recurrence = $eventRecurrenceMapper->getSeriesRecurrence($event);

			$page->setVar('EVENT_REC_FREQ', ucfirst($recurrence['freq']));
			$page->setVar('EVENT_REC_START', $recurrence['start']->getLongDateOnly());
			$page->setVar('EVENT_REC_END', $recurrence['end']->getLongDateOnly());
			$page->parse('recurring_reminder_block', 'recurring_reminder');
			$page->setVar('recurring_dates_block', '');
		}

		/* Activity log
		 */
		$page->setVar(array(
			'activity_log_block' => '',
			'has_activity_log_block' => ''
		));

		$i = 0;

		foreach($event->getMetadata()->getActivityLog() as $currentLog) {

			/* If there are no activityLog items, mappers will spawn a single
			 * object where every property is null
			 *
			 * TODO: Mappers probably shouldn't spawn you such objects
			 */
			if ($currentLog->getId()) {
				$page->setVar(array(
					'ACTIVITY_LOG_CREATED_AT' => $currentLog->getCreatedAt()->getShort(),
					'ACTIVITY_LOG_MESSAGE' => $currentLog->getMessage()
				));

				$page->parse('activity_log_block', 'activity_log', true);

				/* SB-3911
				 */
				$i++;
			}
		}

		/* SB-3911
		 */
		if ($i) {
			$page->parse('has_activity_log_block', 'has_activity_log');
		}

		$page->parse('delete_button_block', 'delete_button');

		/* Cannot add students until an event is created
		 */
		$page->parse('can_add_students_block', 'can_add_students');

		/* Cannot add resources until an event is created
		 */
		$page->parse('can_add_resources_block', 'can_add_resources');
	}
	/* Create new event
	 */
	else {

		/* Set up default dates
		 */
		$event = new \EOTC\Event();
		$event->setupDates($_GET['date']);

		$metadata = new \EOTC\EventMetadata();
		$event->setMetadata($metadata);

		$defaultTypeId = '';

		/* This is used in the checkbox preselect outside this if/else
		 * so we set it to null, to prevent any campuses from being
		 * preselected
		 */
		$defaultCampusId = null;

		/* Miscellaneous setvarring
		 * Hide advanced options by default
		 */
		$page->setVar(array(
			'FUNCTION' => 'Add',
			'EOTC_FUNCTION' => 'Submit for Initial Approval',
			'SHOW_ADVANCED' => '',
			'ADVANCED_SHOW' => '',
			'ADVANCED_CLOSE' => 'hidden',
		));

		$statusMapper = new \EOTC\EventStatusMapper();
		$status = $statusMapper->findDefaultStatus();

		/* You are the default teacher in charge
		 * Even if you're not a teacher
		 */
		$teacherInCharge = array(
			'id' => $_SESSION['user']->getId(),
			'name' => $_SESSION['user']->getFullname(),
		);

		/* -_-
		 */
		$groupHandler = new Group();

		$page->setVar(array(
			'EVENT_RAMS_FILE' => '',
			'EVENT_CURRENT_STUDENTS' => 0,
			'EVENT_TEACHER_IN_CHARGE' => json_encode($teacherInCharge),
			'EVENT_STAFF' => json_encode(array()),
			'EVENT_STATUS' => $status->getName(),
			'EVENT_RAMSREQUIRED_SELECTED' => 'checked="checked"',
			'EVENT_RAMS_UPLOADED' => 0,
			'EVENT_ID' => '',
			'EVENT_AUTHOR_ID' => $_SESSION['user']->getId(),
			'EVENT_NOTE' => '',
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
			'EVENT_LOCATION' => '',
			'EVENT_DETAIL' => '',
			'EVENT_REDIRECT' => $event->setupRedirect($_GET['returnTo'], $event->getStartDateTime()->getUsingFormat('d-m-Y'), $_GET['sid'], $_GET['userId']),
			'EVENT_ALLDAY_CHECKED' => 'checked="checked"',
			'EVENT_STATUS_CLASS' => $status->getStyle(),
			'STUDENT_GROUP_TYPE_IDS' => implode(';', $groupHandler->getStudentGroupTypeIds()),
			'STAFF_GROUP_TYPE_IDS' =>  implode(';', $groupHandler->getStaffGroupTypeIds()),
		));

		/* Recurrence
		 */
		$page->setVar(array(
			'recurring_reminder_block' => '',
			'delete_button_block' => ''
		));

		$page->parse('recurring_dates_block', 'recurring_dates');

		/* Activity Logs
		 */
		$page->setVar(array(
			'has_activity_log_block' => '',
			'activity_log_block' => ''
		));

		/* Cannot add students until an event is created
		 */
		$page->setVar(array(
			'can_add_students_block' => ''
		));

		/* Cannot add resources until an event is created
 		 */
		$page->setVar(array(
			'can_add_resources_block' => ''
		));

	}

	/* Event Types
	 */
	$calendarEventTypeMapper = new \EOTC\EventTypeMapper();
	$allEventTypes = $calendarEventTypeMapper->findAll();

	$page->dropDown('type_drop', $allEventTypes, $defaultTypeId, TEMPLATE_content, array(
		'id' => 'EVENT_EVENTTYPE_ID',
		'type' => 'EVENT_EVENTTYPE_NAME'
	));

	/* Campus Checkbox Array when using Campus Event Level
	 */
	$campusMapper = new \Schoolbox\CampusMapper();
	$campusList = $campusMapper->findAll();

	if ($campusList->count()) {
		$allCampuses = array();

		foreach($campusList as $current) {
			$allCampuses[] = array(
				'id' => $current->getId(),
				'name' => $current->getName()
			);
		}

		$page->checkList('event_campus_list', $allCampuses, $defaultCampusId, TEMPLATE_content, array(
			'id' => 'EVENT_CAMPUS_ID',
			'name' => 'EVENT_CAMPUS_NAME',
		));
	}
	else {
		$page->setBlock(TEMPLATE_content, 'event_campus_list', 'event_campus_list_block');
		$page->setVar('event_campus_list_block', '');
	}

	$page->outputPage();
}
else {
	error(19);
}