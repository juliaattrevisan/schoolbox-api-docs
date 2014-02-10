<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
$mapper = new \EOTC\EventMapper();

try {
	$event = $mapper->findById($this->arg[0]);
}
catch (\Exception $e) {
	error(160);
}

$this->setFile(TEMPLATE_content, 'modules/eotc/status/modify.html');
$this->setBlock(TEMPLATE_content, 'event_campus', 'event_campus_block');
$this->setBlock(TEMPLATE_content, 'event_staff', 'event_staff_block');
$this->setBlock(TEMPLATE_content, 'event_student', 'event_student_block');
$this->setBlock(TEMPLATE_content, 'has_rams', 'has_rams_block');
$this->setBlock(TEMPLATE_content, 'has_event_activity_log', 'has_event_activity_log_block');
$this->setBlock('has_event_activity_log', 'event_activity_log', 'event_activity_log_block');
$this->setBlock(TEMPLATE_content, 'event_status', 'event_status_block');

$this->setVar(array(
	'EVENT_ID' => $event->getId(),
	'EVENT_NOTE' => $event->getNote(),
	'EVENT_LOCATION' => $event->getLocation(),
	'EVENT_DETAIL' => $event->getDetail(),
	'EVENT_CREATED_AT' => $event->getMetadata()->getCreatedAt()->getShort(),
	'EVENT_START' => $event->getStartDateTime()->getShort(),
	'EVENT_END' => $event->getEndDateTime()->getShort(),
	'EVENT_STATUS' => $event->getMetadata()->getStatus()->getName(),
));

/* RAMS file requirement
 */
$this->setVar(array(
	'has_rams_block' => ''
));

if ($event->getMetadata()->getRamsRequired()) {
	$mimetypeMapper = new \Schoolbox\File\MimetypeMapper();
	$mimetype = $mimetypeMapper->getByFilename($event->getRamsFiles()->first()->getFilename());

	$this->setVar(array(
		'EVENT_RAMS_FILE' => $event->getRamsFiles()->first()->getFilename(),
		'EVENT_RAMS_FILESIZE' => $event->getRamsFiles()->first()->getFilesize(),
		'EVENT_RAMS_MIMETYPE_ICONFILE' => $mimetype->getIconFile(),
		'EVENT_RAMS_HASH' => $event->getRamsFiles()->first()->getHash(),
	));

//	var_dump($event->getRamsFiles()->first());
	$this->parse('has_rams_block', 'has_rams');
}

/* Teacher In Charge
 */
$this->setVar(array(
	'EVENT_TEACHER_IN_CHARGE_ID' => $event->getTeacherInCharge()->getId(),
	'EVENT_TEACHER_IN_CHARGE_EXTERNAL_ID' => $event->getTeacherInCharge()->getExternalId(),
	'EVENT_TEACHER_IN_CHARGE_NAME' => $event->getTeacherInCharge()->getFullname(),
));

/* Author
 */
$this->setVar(array(
	'EVENT_AUTHOR_ID' => $event->getAuthor()->getId(),
	'EVENT_AUTHOR_NAME' => $event->getAuthor()->getFullname(),
));

/* Staff Attendees
 */
$this->setVar(array(
	'event_staff_block' => '',
	'EVENT_STAFF_COUNT' => $event->getStaffAttendees()->count()
));

foreach($event->getStaffAttendees() as $currentStaff) {
	$this->setVar(array(
		'EVENT_STAFF_ID' => $currentStaff->getId(),
		'EVENT_STAFF_EXTERNAL_ID' => $currentStaff->getExternalId(),
		'EVENT_STAFF_NAME' => $currentStaff->getFullname(),
	));

	$this->parse('event_staff_block', 'event_staff', true);
}

/* Student Attendees
 */
$this->setVar(array(
	'event_student_block' => '',
	'EVENT_STUDENT_COUNT' => $event->getStudentAttendees()->count()
));

foreach($event->getStudentAttendees() as $currentStudent) {
	$this->setVar(array(
		'EVENT_STUDENT_ID' => $currentStudent->getId(),
		'EVENT_STUDENT_EXTERNAL_ID' => $currentStudent->getExternalId(),
		'EVENT_STUDENT_NAME' => $currentStudent->getFullname(),
	));

	$this->parse('event_student_block', 'event_student', true);
}

/* Related campuses
 */
$this->setVar(array(
	'event_campus_block' => ''
));

foreach($event->getCampusCollection() as $currentCampus) {
	$this->setVar(array(
		'EVENT_CAMPUS_NAME' => $currentCampus->getName()
	));

	$this->parse('event_campus_block', 'event_campus', true);
}

/* Activity Log
 */
$this->setVar(array(
	'event_activity_log_block' => '',
	'has_event_activity_log_block' => ''
));

$i = 0;

foreach($event->getMetadata()->getActivityLog() as $currentLog) {

	/* If there are no activityLog items, mappers will spawn a single
	 * object where every property is null
	 *
	 * TODO: Mappers probably shouldn't spawn you such objects
	 */
	if ($currentLog->getId()) {
		$this->setVar(array(
			'EVENT_ACTIVITY_LOG_CREATED_AT' => $currentLog->getCreatedAt()->getShort(),
			'EVENT_ACTIVITY_LOG_MESSAGE' => $currentLog->getMessage()
		));

		$this->parse('event_activity_log_block', 'event_activity_log', true);

		/* SB-3911
		 */
		$i++;
	}
}

/* SB-3911
 */
if ($i) {
	$this->parse('has_event_activity_log_block', 'has_event_activity_log');
}

/* Status Dropdown
 */
$eventStatusMapper = new \EOTC\EventStatusMapper();
$allEventStatus = $eventStatusMapper->findAll();

foreach($allEventStatus as $currentStatus) {
	$this->setVar(array(
		'EVENT_STATUS_ID' => $currentStatus->getId(),
		'EVENT_STATUS_NAME' => $currentStatus->getName(),
		'EVENT_STATUS_SELECTED' => '',
	));

	/* Preselect
	 */
	if ($currentStatus->getId() == $event->getMetadata()->getStatus()->getId()) {
		$this->setVar(array(
			'EVENT_STATUS_SELECTED' => 'selected="selected"',
		));
	}

	$this->parse('event_status_block', 'event_status', true);
}

/* Status Comment
 */
$this->setVar(array(
	'EVENT_COMMENT' => $event->getMetadata()->getComment(),
));

//new \Alaress_Probe($event);