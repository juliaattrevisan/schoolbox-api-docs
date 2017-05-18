<?php
/**
 * Manage Modify
 *
 * Modify EOTC events
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if (isset($this)) {
    $page = $this;
} else {
    /* Direct access to modify.php -_-
     */
    /** @var Alaress\Application\Kernel $kernel */
    $kernel = require_once(__DIR__ . '/../../../../library/Bootstrap.php');

    /** @var SitePage $page */
    $page = $kernel->getDependencyInjector()->get('sitePage');
}

$page->setFile(TEMPLATE_content, 'modules/eotc/manage/modify.html');
$page->setBlock(TEMPLATE_content, 'recurring_dates', 'recurring_dates_block');
$page->setBlock(TEMPLATE_content, 'recurring_reminder', 'recurring_reminder_block');
$page->setBlock(TEMPLATE_content, 'has_activity_log', 'has_activity_log_block');
$page->setBlock('has_activity_log', 'activity_log', 'activity_log_block');
$page->setBlock(TEMPLATE_content, 'can_add_students', 'can_add_students_block');
$page->setBlock(TEMPLATE_content, 'can_add_resources', 'can_add_resources_block');
$page->setBlock('can_add_resources', 'resource', 'resource_block');
$page->setBlock(TEMPLATE_content, 'can_promote_in_principle', 'can_promote_in_principle_block');

$authenticator = new \EOTC\Authenticator($this);

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

$defaultTypeId = '';

$configMapper = \EOTC\ConfigMapper::getInstance();
try {
    $ramsWarning = $configMapper->findOneByColumn('name', 'rams_warning')->getValue();
} catch (\Schoolbox\Mapper\Exception $e) {
    $ramsWarning = '';
}

$page->setVar([
    'ATTACH_ZONE'  => '',
    'RAMS_WARNING' => Scrub::html($ramsWarning),
]);

/* Modify existing event
 */
if (is_numeric($_GET['eventID'])) {
    $mapper = \EOTC\EventMapper::getInstance();

    try {
        /** @var EOTC\Event $event */
        $event = $mapper->findById($_GET['eventID']);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(160);
    }

    /* Edit rules
	 */
    $canAdminister = $authenticator->isEOTCAdministrator($sessionUser, $event);
    $isAuthor = (($event->getAuthor()->getId() == $sessionUser->getId()) && $authenticator->isEOTCCreator($sessionUser));
    $isTeacherInCharge = ($event->getTeacherInCharge() instanceof \Schoolbox\User) && ($event->getTeacherInCharge()->getId() == $sessionUser->getId());

    /* Authors cannot view an event that is in Final Approval
     * Instead redirect to a different place
     *
     * Unless you're an EOTC administrator, then you can still
     * modify it (provided you've the appropriate permissions)
     */
    if ($isAuthor && !$canAdminister && $event->getMetadata()->getStatus()->isApproved()) {
        $this->localRedir("/modules/eotc/status/modify/{$event->getId()}");
    }
    /* EOTC Administrators and event authors can edit this event
	 */
    else if ($canAdminister || $isAuthor || $isTeacherInCharge) {

        /* Do a check for missing metadata
		 */
        if (!$event->getMetadata()) {
            throw new \RuntimeException('No metadata record found for this event.');
        }

        /* Default values
		 */
        $defaultTypeId = $event->getType()->getId();
        $defaultCampusId = array();

        $allCampuses = $event->getCampusCollection()->getAll();

        /** @var \Schoolbox\Campus $currentCampus */
        foreach ($allCampuses as $currentCampus) {
            $defaultCampusId[] = $currentCampus->getId();
        }

        /* Default magicsuggest values
		 */
        $staffSelected = array();

        /** @var \Schoolbox\User $currentStaff */
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
        } else {
            $teacherInCharge = array();
        }

        /* Miscellaneous setvarring
		 * Hide advanced options by default
		 */
        $page->setVar(array(
            'FUNCTION' => 'Update',
            'EOTC_FUNCTION' => 'Update Event',
            'SHOW_ADVANCED' => 'hide',
            'ADVANCED_SHOW' => '',
            'ADVANCED_CLOSE' => 'hidden',
        ));

        // RAM in the RAMS
        if ($event->getMetadata()->getRamsRequired()) {
            $hashes = [];

            foreach ($event->getMetadata()->getRamsFiles() as $ramsFile) {
                $hashes[] = $ramsFile->getHash();
            }

            $zone = new \Schoolbox\Resource\Attachment\Zone('ramsZone');
            /** @var Schoolbox\L10n\RosettaStone $l10n */
            $l10n = $page->getDi()->get('l10n');
            $zone
                ->setMulti(true)
                ->attachFiles($hashes)
                ->setThumbnailSize(64)
                ->setModalTitle($l10n->translateSentence('Select {:eotcRamsAbbreviation} files'))
                ->setMimeFilter('*/*')
                ->setAttachPrompt('Attach files')
                ->renderIntoSitePage($page, 'ATTACH_ZONE');
        }

        /* -_-
		 */
        $groupHandler = new Group();

        $page->setVar(array(
            'EVENT_CURRENT_STUDENTS' => $event->getMetadata()->getStudentAttendees()->count(),
            'EVENT_TEACHER_IN_CHARGE' => json_encode($teacherInCharge),
            'EVENT_STAFF' => json_encode($staffSelected),
            'EVENT_STATUS' => $event->getMetadata()->getStatus()->getName(),
            'EVENT_STATUS_CLASS' => $event->getMetadata()->getStatus()->getStyle(),
            'EVENT_RAMSREQUIRED_SELECTED' => $event->getMetadata()->getRamsRequired() ? 'checked="checked"' : '',
            'EVENT_RAMS_UPLOADED' => $event->getMetadata()->getRamsFiles()->count() ? 1 : 0,
            'EVENT_ID' => $event->getId(),
            'EVENT_AUTHOR_ID' => $event->getAuthor()->getId(),
            'EVENT_TITLE' => $event->getTitle(),
            'EVENT_TIMEINPUTS' => $event->getAllDay() ? 'hide' : '',
            'EVENT_START_DATE' => $event->getStartDateTime()->getLocalShort(),
            'EVENT_START_HOUR' => $event->getStartDateTime()->get12Hour(),
            'EVENT_START_MIN' => $event->getStartDateTime()->getMinute(true),
            'EVENT_START_AM' => $event->getStartDateTime()->isAM() ? 'selected="selected"' : '',
            'EVENT_START_PM' => $event->getStartDateTime()->isPM() ? 'selected="selected"' : '',
            'EVENT_END_DATE' => $event->getEndDateTime()->getLocalShort(),
            'EVENT_END_HOUR' => $event->getEndDateTime()->get12Hour(),
            'EVENT_END_MIN' => $event->getEndDateTime()->getMinute(true),
            'EVENT_END_AM' => $event->getEndDateTime()->isAM() ? 'selected="selected"' : '',
            'EVENT_END_PM' => $event->getEndDateTime()->isPM() ? 'selected="selected"' : '',
            'EVENT_ALLDAY_CHECKED' => $event->getAllDay() ? 'checked="checked"' : '',
            'EVENT_LOCATION' => $event->getLocation(),
            'EVENT_DETAIL' => $event->getDetail(),
            'EVENT_REDIRECT' => $event->setupRedirect($_GET['returnTo'], $event->getStartDateTime()->getUsingFormat('d-m-Y'), $_GET['sid'], $_GET['userId']),
            'STUDENT_GROUP_TYPE_IDS' => implode(';', $groupHandler->getStudentGroupTypeIds()),
            'STAFF_GROUP_TYPE_IDS' =>  implode(';', $groupHandler->getStaffGroupTypeIds()),
            'EVENT_RECURRENCE_COLLAPSE_STATE' => 'collapsed',
            'EVENT_RECURRENCE_COLLAPSE_CLASS' => 'hide',
        ));

        /* Recurrence
		 */
        // Is there a recurrence? If so don't show the repeat selector.
        $recurrence = array();

        if ($event->getSeriesId()) {
            $eventRecurrenceMapper = new \Schoolbox\Calendar\EventRecurrenceMapper($mapper);
            $recurrence = $eventRecurrenceMapper->getSeriesRecurrence($event);
        }

        /** @var $recurrenceStart \Alaress_Date_Format */
        /** @var $recurrenceEnd \Alaress_Date_Format */
        if (!empty($recurrence)) {
            $recurrenceStart = $recurrence['start'];
            $recurrenceEnd = $recurrence['end'];

            $page->setVar('EVENT_REC_FREQ', ucfirst($recurrence['freq']));
            $page->setVar('EVENT_REC_START', $recurrenceStart->getLongDateOnly());
            $page->setVar('EVENT_REC_END', $recurrenceEnd->getLongDateOnly());
            $page->parse('recurring_reminder_block', 'recurring_reminder');
            $page->setVar('recurring_dates_block', '');
        } else {
            $page->setVar('recurring_reminder_block', '');
            $page->parse('recurring_dates_block', 'recurring_dates');
        }

        /* Resources
		 */
        if ($event->getMetadata()->getResourceBookings()->count()) {
            $resourceMapper = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
            $resourceBookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();

            /* TODO: Optimise this!
             */
            foreach ($event->getMetadata()->getResourceBookings() as $currentResourceBooking) {
                $resource = $resourceMapper->findById($currentResourceBooking->getResource()->getId());
                $resourceBooking = $resourceBookingMapper->findById($currentResourceBooking->getId());

                $page->setVar(array(
                    'RESOURCE_ID' => $resource->getId(),
                    'RESOURCE_NAME' => $resource->getName()
                ));

                $page->parse('resource_block', 'resource', true);
            }
        } else {
            $page->setVar(array(
                'resource_block' => ''
            ));
        }

        /* Activity log
		 */
        $page->setVar(array(
            'activity_log_block' => '',
            'has_activity_log_block' => ''
        ));

        $i = 0;

        /** @var \EOTC\ActivityLog\Log $currentLog */
        foreach ($event->getMetadata()->getActivityLog() as $currentLog) {

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

        /* Cannot add students until an event is created
		 */
        $page->parse('can_add_students_block', 'can_add_students');

        /* Cannot add resources until an event is created
		 */
        $page->parse('can_add_resources_block', 'can_add_resources');

        /* Cannot self-promote until a status is In Principle
		 * Only an author can do it to his own event
		 */
        $authorSelfPromote = $isAuthor && $authenticator->canSelfPromote($sessionUser, $event);
        $teacherInChargeSelfPromote = $isTeacherInCharge && $authenticator->canSelfPromote($event->getTeacherInCharge(), $event);

        if ($authorSelfPromote || $teacherInChargeSelfPromote) {
            $page->parse('can_promote_in_principle_block', 'can_promote_in_principle');
        } else {
            $page->setVar(array(
                'can_promote_in_principle_block' => ''
            ));
        }
    } else {
        error(19);
    }
}
/* Create new event
 */
elseif ($authenticator->isEOTCCreator($sessionUser)) {

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
        'SHOW_ADVANCED' => 'hide',
        'ADVANCED_SHOW' => '',
        'ADVANCED_CLOSE' => 'hidden',
    ));

    $status = \EOTC\EventStatusMapper::getInstance()->findDefaultStatus();

    /* You are the default teacher in charge
	 * Even if you're not a teacher
	 */
    $teacherInCharge = array(
        'id' => $sessionUser->getId(),
        'name' => $sessionUser->getFullname(),
    );

    /* -_-
	 */
    $groupHandler = new Group();

    /* Set an arbitrary start/end time initially
     */
    $page->setVar(array(
        'EVENT_RAMS_FILE' => '',
        'EVENT_CURRENT_STUDENTS' => 0,
        'EVENT_TEACHER_IN_CHARGE' => json_encode($teacherInCharge),
        'EVENT_STAFF' => json_encode(array()),
        'EVENT_STATUS' => $status->getName(),
        'EVENT_RAMSREQUIRED_SELECTED' => 'checked="checked"',
        'EVENT_RAMS_UPLOADED' => 0,
        'EVENT_ID' => '',
        'EVENT_AUTHOR_ID' => $sessionUser->getId(),
        'EVENT_TITLE' => '',
        'EVENT_TIMEINPUTS' => 'hide',
        'EVENT_START_DATE' => $event->getStartDateTime()->getLocalShort(),
        'EVENT_START_HOUR' => '9',
        'EVENT_START_MIN' => '00',
        'EVENT_START_AM' => '',
        'EVENT_START_PM' => '',
        'EVENT_END_DATE' => $event->getEndDateTime()->getLocalShort(),
        'EVENT_END_HOUR' => '10',
        'EVENT_END_MIN' => '00',
        'EVENT_END_AM' => '',
        'EVENT_END_PM' => '',
        'EVENT_LOCATION' => '',
        'EVENT_DETAIL' => '',
        'EVENT_REDIRECT' => $event->setupRedirect($_GET['returnTo'], $event->getStartDateTime()->getUsingFormat('d-m-Y'), $_GET['sid'], $_GET['userId']),
        'EVENT_ALLDAY_CHECKED' => 'checked="checked"',
        'EVENT_STATUS_CLASS' => $status->getStyle(),
        'STUDENT_GROUP_TYPE_IDS' => implode(';', $groupHandler->getStudentGroupTypeIds()),
        'STAFF_GROUP_TYPE_IDS' =>  implode(';', $groupHandler->getStaffGroupTypeIds()),
        'EVENT_RECURRENCE_COLLAPSE_STATE' => 'collapsed',
        'EVENT_RECURRENCE_COLLAPSE_CLASS' => 'hide',
    ));

    /* Recurrence
	 */
    $page->setVar(array(
        'recurring_reminder_block' => '',
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

    /* Cannot self-promote until a status is In Principle
 	 */
    $page->setVar(array(
        'can_promote_in_principle_block' => ''
    ));
}
/* Nobody else should be here
 */
else {
    error(19);
}

/* Event Types
 */
$allEventTypes = \EOTC\EventTypeMapper::getInstance()->findAll();

$page->dropDown('type_drop', $allEventTypes, $defaultTypeId, TEMPLATE_content, array(
    'id' => 'EVENT_EVENTTYPE_ID',
    'type' => 'EVENT_EVENTTYPE_NAME'
));

/* Campus Checkbox Array when using Campus Event Level
 */
$campusList = \Schoolbox\CampusMapper::getInstance()->findAll();

if ($campusList->count()) {
    $allCampuses = array();

    foreach ($campusList as $current) {
        $allCampuses[] = array(
            'id' => $current->getId(),
            'name' => $current->getName()
        );
    }

    $page->checkList('event_campus_list', $allCampuses, $defaultCampusId, TEMPLATE_content, array(
        'id' => 'EVENT_CAMPUS_ID',
        'name' => 'EVENT_CAMPUS_NAME',
    ));
} else {
    $page->setBlock(TEMPLATE_content, 'event_campus_list', 'event_campus_list_block');
    $page->setVar('event_campus_list_block', '');
}

// Legacy support for direct access to modify.php
if(!isset($this)){
    $page->outputPage();
}
