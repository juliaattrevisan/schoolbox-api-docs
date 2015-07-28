<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* Only EOTC Administrators can view this page
 */
$authenticator = new \EOTC\Authenticator();

$mapper = new \EOTC\EventMapper();

try {
    $event = $mapper->findById($this->arg[0]);
} catch (\Exception $e) {
    error(160);
}

if (!$authenticator->isEOTCAdministrator($_SESSION['user']) && !$authenticator->canPromote($_SESSION['user'], $event)) {
    error(19);
}

$this->setFile(TEMPLATE_content, 'modules/eotc/status/modify.html');
$this->setBlock(TEMPLATE_content, 'event_campus', 'event_campus_block');
$this->setBlock(TEMPLATE_content, 'event_staff', 'event_staff_block');
$this->setBlock(TEMPLATE_content, 'event_student', 'event_student_block');
$this->setBlock(TEMPLATE_content, 'has_rams', 'has_rams_block');
$this->setBlock('has_rams', 'rams_list', 'rams_list_block');
$this->setBlock(TEMPLATE_content, 'has_event_activity_log', 'has_event_activity_log_block');
$this->setBlock('has_event_activity_log', 'event_activity_log', 'event_activity_log_block');
$this->setBlock(TEMPLATE_content, 'has_change_event_status', 'has_change_event_status_block');
$this->setBlock('has_change_event_status', 'event_status', 'event_status_block');
$this->setBlock(TEMPLATE_content, 'recurring_reminder', 'recurring_reminder_block');
$this->setBlock(TEMPLATE_content, 'event_resourcebooking', 'event_resourcebooking_block');

$this->setVar(array(
    'EVENT_ID' => $event->getId(),
    'EVENT_TITLE' => $event->getTitle(),
    'EVENT_LOCATION' => $event->getLocation(),
    'EVENT_DETAIL' => $event->getDetail(),
    'EVENT_CREATED_AT' => $event->getMetadata()->getCreatedAt()->getShort(),
    'EVENT_DATESTAMP' => $event->getStartDateTime()->getURLDate(),
    'EVENT_START' => $event->getStartDateTime()->getShort(),
    'EVENT_END' => $event->getEndDateTime()->getShort(),
    'EVENT_STATUS' => $event->getMetadata()->getStatus()->getName(),
    'EVENT_STATUS_CLASS' => $event->getMetadata()->getStatus()->getStyle(),
));

/* Recurrence
 */
// Is there a recurrence? If so don't show the repeat selector.
$recurrence = array();
if ($event->getSeriesId()) {
    $eventRecurrenceMapper = new \Schoolbox\Calendar\EventRecurrenceMapper($mapper);
    $recurrence = $eventRecurrenceMapper->getSeriesRecurrence($event);
}
if (!empty($recurrence)) {
    $this->setVar(array(
        'EVENT_REC_FREQ' => ucfirst($recurrence['freq']),
        'EVENT_REC_START' => $recurrence['start']->getLongDateOnly(),
        'EVENT_REC_END' => $recurrence['end']->getLongDateOnly(),
    ));
    $this->parse('recurring_reminder_block', 'recurring_reminder');
} else {
    $this->setVar(array(
        'recurring_reminder_block' => ''
    ));
}

/* RAMS file requirement
 */
$this->setVar(array(
    'has_rams_block' => '',
    'rams_list_block' => '',
));

if ($event->getMetadata()->getRamsRequired()) {
/* Sometimes event/file relations get recorded with a storage_id of 0
	 * so we need to check whether one actually legitimately saved properly
	 */
    foreach ($event->getMetadata()->getRamsFiles() as $ramsFile) {
        $mimetypeMapper = new \Schoolbox\File\MimetypeMapper();
        $mimetype = $mimetypeMapper->getByFilename($ramsFile->getBasename());

        $this->setVar(array(
            'EVENT_RAMS_FILE' => $ramsFile->getBasename(),
            'EVENT_RAMS_FILESIZE' => $ramsFile->getSize(),
            'EVENT_RAMS_MIMETYPE_ICONFILE' => $mimetype->getIconFile(),
            'EVENT_RAMS_HASH' => $ramsFile->getHash(),
        ));

        $this->parse('rams_list_block', 'rams_list', true);
    }
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
    'EVENT_STAFF_COUNT' => $event->getMetadata()->getStaffAttendees()->count()
));

foreach ($event->getMetadata()->getStaffAttendees() as $currentStaff) {
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
    'EVENT_STUDENT_COUNT' => $event->getMetadata()->getStudentAttendees()->count()
));

foreach ($event->getMetadata()->getStudentAttendees() as $currentStudent) {
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

foreach ($event->getCampusCollection() as $currentCampus) {
    $this->setVar(array(
        'EVENT_CAMPUS_NAME' => $currentCampus->getName()
    ));

    $this->parse('event_campus_block', 'event_campus', true);
}

/* Resources
 */
$this->setVar(array(
    'EVENT_RESOURCE_COUNT' => $event->getMetadata()->getResourceBookings()->count(),
    'event_resourcebooking_block' => ''
));

if ($event->getMetadata()->getResourceBookings()->count()) {
    $resourceMapper = new \Schoolbox\ResourceBooking\Item\Mapper();
    $resourceBookingMapper = new \Schoolbox\ResourceBooking\Booking\Mapper();

    foreach ($event->getMetadata()->getResourceBookings() as $currentResourceBooking) {
/* TODO: Optimise this!
		 */
        $resource = $resourceMapper->findById($currentResourceBooking->getResource()->getId());
        $resourceBooking = $resourceBookingMapper->findById($currentResourceBooking->getId());

        $this->setVar(array(
            'EVENT_RESOURCEBOOKING_ID' => $resourceBooking->getId(),
            'EVENT_RESOURCEBOOKING_NAME' => $resourceBooking->getName(),
            'EVENT_RESOURCEBOOKING_START' => $resourceBooking->getEvent()->getStartDateTime()->getShort(),
            'EVENT_RESOURCEBOOKING_END' => $resourceBooking->getEvent()->getEndDateTime()->getShort(),
            'EVENT_RESOURCE_ID' => $resource->getId(),
            'EVENT_RESOURCE_NAME' => $resource->getName()
        ));

        $this->parse('event_resourcebooking_block', 'event_resourcebooking', true);
    }
}

/* Activity Log
 */
$this->setVar(array(
    'event_activity_log_block' => '',
    'has_event_activity_log_block' => ''
));

$i = 0;

foreach ($event->getMetadata()->getActivityLog() as $currentLog) {
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

/* Status section
 *
 * If you don't have permissions to the status of the event, you don't get
 * access to this section anymore
 */

/* Status Comment
 */
$this->setVar(array(
    'EVENT_COMMENT' => $event->getMetadata()->getComment(),
));

if ($authenticator->canPromote($_SESSION['user'], $event)) {
    $eventStatusMapper = new \EOTC\EventStatusMapper();

    /* Soops have access to all statuses
	 */
    if ($_SESSION['user']->isSuperuser()) {
        $allEventStatus = $eventStatusMapper->findAll();
    } else {
        $allEventStatus = $eventStatusMapper->findAvailableStatuses($event);
    }

    /* Status Dropdown
	 *
	 * Limited to the current status and the next one
	 *
	 * Limited also by user's ability to set that status, as defined
	 * in the EOTC permissions
	 */
    foreach ($allEventStatus as $currentStatus) {
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

    $this->parse('has_change_event_status_block', 'has_change_event_status');
} else {
    $this->setVar(array(
        'has_change_event_status_block' => ''
    ));
}
