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

use EOTC\ActivityLog\Log as ActivityLog;
use EOTC\Authenticator;
use EOTC\ConfigMapper;
use EOTC\Event;
use EOTC\EventMapper;
use EOTC\EventMetadata;
use EOTC\EventStatusMapper;
use EOTC\EventTypeMapper;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Calendar\EventRecurrenceMapper as EventRecurrenceMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Zone as Attachzone;
use Schoolbox\ResourceBooking\Item\Mapper as ItemMapper;
use Schoolbox\ResourceBooking\Booking\Mapper as BookingMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;

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
$page->setBlock(TEMPLATE_content, 'can_permission_slip_required', 'can_permission_slip_required_block');

$authenticator = new Authenticator($this);

/** @var User $sessionUser */
$sessionUser = $_SESSION['user'];

/* Event type
 */
$defaultTypeId = '';

/* This is used in the checkbox preselect outside this if/else
 * so we set it to null, to prevent any campuses from being
 * preselected
 */
$defaultCampusId = [];

/* This is used in the checkbox preselect outside this if/else
 * so we set it to null, to prevent any roles from being preselected
 */
$selectedRoles = [];

$configMapper = ConfigMapper::getInstance();

try {
    $ramsWarning = $configMapper->findOneByColumn('name', 'rams_warning')->getValue();
} catch (MapperException $e) {
    $ramsWarning = '';
}

$page->setVar(
    [
        'ATTACH_ZONE'  => '',
        'RAMS_WARNING' => Scrub::html($ramsWarning),
    ]
);

/* Modify existing event
 */
if (is_numeric($_GET['eventID'])) {
    $mapper = EventMapper::getInstance();

    try {
        /** @var Event $event */
        $event = $mapper->findById($_GET['eventID']);
    } catch (MapperException $e) {
        error(ErrorMapper::ERROR_EVENT_NOT_FOUND);
    }

    /* Edit rules
	 */
    $canAdminister     = $authenticator->isEOTCAdministrator($sessionUser, $event);
    $isAuthor          = (($event->getAuthor()->getId() == $sessionUser->getId()) && $authenticator->isEOTCCreator(
            $sessionUser
        ));
    $isTeacherInCharge = ($event->getTeacherInCharge() instanceof User) && ($event->getTeacherInCharge()->getId(
            ) == $sessionUser->getId());

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
    elseif ($canAdminister || $isAuthor || $isTeacherInCharge) {

        /* Do a check for missing metadata
         */
        if (!$event->getMetadata()) {
            throw new \RuntimeException('No metadata record found for this event.');
        }

        /* Default values
         */
        $defaultTypeId = $event->getType()->getId();

        $allCampuses = $event->getCampusCollection()->getAll();

        /** @var Campus $currentCampus */
        foreach ($allCampuses as $currentCampus) {
            $defaultCampusId[] = $currentCampus->getId();
        }

        $allRoles = $event->getRoleCollection()->getAll();

        /** @var Role $currentRole */
        foreach ($allRoles as $currentRole) {
            $selectedRoles[] = $currentRole->getId();
        }

        /* Default magicsuggest values
         */
        $staffSelected = [];

        /** @var User $currentStaff */
        foreach ($event->getMetadata()->getStaffAttendees() as $currentStaff) {
            $staffSelected[] = [
                'id'   => $currentStaff->getId(),
                'name' => $currentStaff->getFullname()
            ];
        }

        /* Teacher in charge
         */
        if ($event->getTeacherInCharge()->getId()) {
            $teacherInCharge = [
                'id'   => $event->getTeacherInCharge()->getId(),
                'name' => $event->getTeacherInCharge()->getFullname(),
            ];
        } else {
            $teacherInCharge = [];
        }

        /* Miscellaneous setvarring
         * Hide advanced options by default
         */
        $page->setVar(
            [
                'FUNCTION'       => 'Update',
                'EOTC_FUNCTION'  => 'Update Event',
                'SHOW_ADVANCED'  => 'hide',
                'ADVANCED_SHOW'  => '',
                'ADVANCED_CLOSE' => 'hidden',
            ]
        );

        // RAM in the RAMS
        if ($event->getMetadata()->getRamsRequired()) {
            $hashes = [];

            foreach ($event->getMetadata()->getRamsFiles() as $ramsFile) {
                $hashes[] = $ramsFile->getHash();
            }

            $zone = new Attachzone('ramsZone');
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

        $page->setVar(
            [
                'EVENT_CURRENT_STUDENTS'          => $event->getMetadata()->getStudentAttendees()->count(),
                'EVENT_TEACHER_IN_CHARGE'         => json_encode($teacherInCharge),
                'EVENT_STAFF'                     => json_encode($staffSelected),
                'EVENT_STATUS'                    => $event->getMetadata()->getStatus()->getName(),
                'EVENT_STATUS_CLASS'              => $event->getMetadata()->getStatus()->getStyle(),
                'EVENT_RAMSREQUIRED_SELECTED'     => $event->getMetadata()->getRamsRequired(
                ) ? 'checked="checked"' : '',
                'EVENT_RAMS_UPLOADED'             => $event->getMetadata()->getRamsFiles()->count() ? 1 : 0,
                'EVENT_ID'                        => $event->getId(),
                'EVENT_AUTHOR_ID'                 => $event->getAuthor()->getId(),
                'EVENT_TITLE'                     => $event->getTitle(),
                'EVENT_TIMEINPUTS'                => $event->getAllDay() ? 'hide' : '',
                'EVENT_START_DATE'                => Scrub::text(
                    $event->getAllDay() ? $event->getStartDateTime()->getLocalShort() : $event->getStartDateTime(
                    )->getLocal()
                ),
                'EVENT_END_DATE'                  => Scrub::text(
                    $event->getAllDay() ? $event->getEndDateTime()->getLocalShort() : $event->getEndDateTime(
                    )->getLocal()
                ),
                'EVENT_ALLDAY_CHECKED'            => $event->getAllDay() ? '1' : '0',
                'EVENT_LOCATION'                  => $event->getLocation(),
                'EVENT_DETAIL'                    => $event->getDetail(),
                'EVENT_REDIRECT'                  => $event->setupRedirect(
                    $_GET['returnTo'],
                    $event->getStartDateTime()->getUsingFormat('d-m-Y'),
                    $_GET['sid'],
                    $_GET['userId']
                ),
                'STUDENT_GROUP_TYPE_IDS'          => implode(';', $groupHandler->getStudentGroupTypeIds()),
                'STAFF_GROUP_TYPE_IDS'            => implode(';', $groupHandler->getStaffGroupTypeIds()),
                'EVENT_RECURRENCE_COLLAPSE_STATE' => 'collapsed',
                'EVENT_RECURRENCE_COLLAPSE_CLASS' => 'hide',
            ]
        );

        /* Recurrence
         */
        // Is there a recurrence? If so don't show the repeat selector.
        $recurrence = [];

        if ($event->getSeriesId()) {
            $eventRecurrenceMapper = new EventRecurrenceMapper($mapper);
            $recurrence            = $eventRecurrenceMapper->getSeriesRecurrence($event);
        }

        /** @var $recurrenceStart \Alaress_Date_Format */
        /** @var $recurrenceEnd \Alaress_Date_Format */
        if (!empty($recurrence)) {
            $recurrenceStart = $recurrence['start'];
            $recurrenceEnd   = $recurrence['end'];

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
            $resourceMapper        = ItemMapper::getInstance();
            $resourceBookingMapper = BookingMapper::getInstance();

            /* TODO: Optimise this!
             */
            foreach ($event->getMetadata()->getResourceBookings() as $currentResourceBooking) {
                $resource        = $resourceMapper->findById($currentResourceBooking->getResource()->getId());
                $resourceBooking = $resourceBookingMapper->findById($currentResourceBooking->getId());

                $page->setVar(
                    [
                        'RESOURCE_ID'   => $resource->getId(),
                        'RESOURCE_NAME' => $resource->getName()
                    ]
                );

                $page->parse('resource_block', 'resource', true);
            }
        } else {
            $page->setVar(
                [
                    'resource_block' => ''
                ]
            );
        }

        /* Activity log
         */
        $page->setVar(
            [
                'activity_log_block'     => '',
                'has_activity_log_block' => ''
            ]
        );

        /** @var ActivityLog $currentLog */
        if ($event->getMetadata()->getActivityLog()->count()) {
            foreach ($event->getMetadata()->getActivityLog() as $currentLog) {
                $page->setVar(
                    [
                        'ACTIVITY_LOG_CREATED_AT' => $currentLog->getCreatedAt()->getShort(),
                        'ACTIVITY_LOG_MESSAGE'    => $currentLog->getMessage()
                    ]
                );

                $page->parse('activity_log_block', 'activity_log', true);
            }

            $page->parse('has_activity_log_block', 'has_activity_log');
        }

        /* Permission Slip Required
         */
        try {
            $externalDbSync = ConfigMapper::getInstance()->findOneByColumn('name', 'external_db_sync');

            if ((int)$externalDbSync->getValue() == 1) {
                $this->setVar(
                    [
                        'EVENT_PERMISSION_SLIP_SELECTED' => $event->getMetadata()->getPermissionSlipRequired(
                        ) ? 'checked="checked"' : ''
                    ]
                );

                $this->parse('can_permission_slip_required_block', 'can_permission_slip_required');
            } else {
                $this->setVar(
                    [
                        'can_permission_slip_required_block' => ''
                    ]
                );
            }
        } catch (MapperException $e) {
            $this->setVar(
                [
                    'can_permission_slip_required_block' => ''
                ]
            );
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
        $authorSelfPromote          = $isAuthor && $authenticator->canSelfPromote($sessionUser, $event);
        $teacherInChargeSelfPromote = $isTeacherInCharge && $authenticator->canSelfPromote(
                $event->getTeacherInCharge(),
                $event
            );

        if ($authorSelfPromote || $teacherInChargeSelfPromote) {
            $page->parse('can_promote_in_principle_block', 'can_promote_in_principle');
        } else {
            $page->setVar(
                [
                    'can_promote_in_principle_block' => ''
                ]
            );
        }
    } else {
        error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }
} /* Create new event
 */
elseif ($authenticator->isEOTCCreator($sessionUser)) {

    /* Set up default dates
	 */
    $event = new Event();
    $event->setupDates($_GET['date']);

    $metadata = new EventMetadata();
    $event->setMetadata($metadata);

    /* Miscellaneous setvarring
	 * Hide advanced options by default
	 */
    $page->setVar(
        [
            'FUNCTION'       => 'Add',
            'EOTC_FUNCTION'  => 'Submit for Initial Approval',
            'SHOW_ADVANCED'  => 'hide',
            'ADVANCED_SHOW'  => '',
            'ADVANCED_CLOSE' => 'hidden',
        ]
    );

    $status = EventStatusMapper::getInstance()->findDefaultStatus();

    /* You are the default teacher in charge
	 * Even if you're not a teacher
	 */
    $teacherInCharge = [
        'id'   => $sessionUser->getId(),
        'name' => $sessionUser->getFullname(),
    ];

    /* -_-
	 */
    $groupHandler = new Group();

    /* Set the default start and end times to the nearest hour and an hour later than that, respectively.
     */

    $startDate = Alaress_Date_Format::now();
    $startDateMinutes = $startDate->getMinute();
    $startDateSeconds = $startDate->getSecond();

    $startDate->addHours(1);
    $startDate->addMinutes(-$startDateMinutes);
    $startDate->addSeconds(-$startDateSeconds);

    $endDate = clone $startDate;
    $endDate->addHours(1);

    $event->getStartDateTime()->setDate($startDate->getYear(),$startDate->getMonth(),$startDate->getDay());
    $event->getStartDateTime()->setTime($startDate->getHour(),$startDate->getMinute(),$startDate->getSecond());

    $event->getEndDateTime()->setDate($endDate->getYear(),$endDate->getMonth(),$endDate->getDay());
    $event->getEndDateTime()->setTime($endDate->getHour(),$endDate->getMinute(),$endDate->getSecond());

    $event->setAllDay(false);

    $page->setVar(
        [
            'EVENT_RAMS_FILE'                 => '',
            'EVENT_CURRENT_STUDENTS'          => 0,
            'EVENT_TEACHER_IN_CHARGE'         => json_encode($teacherInCharge),
            'EVENT_STAFF'                     => json_encode([]),
            'EVENT_STATUS'                    => $status->getName(),
            'EVENT_RAMSREQUIRED_SELECTED'     => 'checked="checked"',
            'EVENT_RAMS_UPLOADED'             => 0,
            'EVENT_ID'                        => '',
            'EVENT_AUTHOR_ID'                 => $sessionUser->getId(),
            'EVENT_TITLE'                     => '',
            'EVENT_TIMEINPUTS'                => 'hide',
            'EVENT_START_DATE'                => Scrub::text($event->getStartDateTime()->getLocal()
            ),
            'EVENT_END_DATE'                  => Scrub::text($event->getEndDateTime()->getLocal()),
            'EVENT_LOCATION'                  => '',
            'EVENT_DETAIL'                    => '',
            'EVENT_REDIRECT'                  => $event->setupRedirect(
                $_GET['returnTo'],
                $event->getStartDateTime()->getUsingFormat('d-m-Y'),
                $_GET['sid'],
                $_GET['userId']
            ),
            'EVENT_ALLDAY_CHECKED'            => $event->getAllDay() ? '1' : '0',
            'EVENT_STATUS_CLASS'              => $status->getStyle(),
            'STUDENT_GROUP_TYPE_IDS'          => implode(';', $groupHandler->getStudentGroupTypeIds()),
            'STAFF_GROUP_TYPE_IDS'            => implode(';', $groupHandler->getStaffGroupTypeIds()),
            'EVENT_RECURRENCE_COLLAPSE_STATE' => 'collapsed',
            'EVENT_RECURRENCE_COLLAPSE_CLASS' => 'hide',
        ]
    );

    /* Recurrence
	 */
    $page->setVar(
        [
            'recurring_reminder_block' => '',
        ]
    );

    $page->parse('recurring_dates_block', 'recurring_dates');

    /* Activity Logs
	 */
    $page->setVar(
        [
            'has_activity_log_block' => '',
            'activity_log_block'     => ''
        ]
    );

    /* Permission Slip Required
     */
    try {
        $externalDbSync = ConfigMapper::getInstance()->findOneByColumn('name', 'external_db_sync');

        if ((int)$externalDbSync->getValue() == 1) {
            $this->setVar(
                [
                    'EVENT_PERMISSION_SLIP_SELECTED' => $event->getMetadata()->getPermissionSlipRequired(
                    ) ? 'checked="checked"' : ''
                ]
            );

            $this->parse('can_permission_slip_required_block', 'can_permission_slip_required');
        } else {
            $this->setVar(
                [
                    'can_permission_slip_required_block' => ''
                ]
            );
        }
    } catch (MapperException $e) {
        $this->setVar(
            [
                'can_permission_slip_required_block' => ''
            ]
        );
    }

    /* Cannot add students until an event is created
	 */
    $page->setVar(
        [
            'can_add_students_block' => ''
        ]
    );

    /* Cannot add resources until an event is created
	 */
    $page->setVar(
        [
            'can_add_resources_block' => ''
        ]
    );

    /* Cannot self-promote until a status is In Principle
 	 */
    $page->setVar(
        [
            'can_promote_in_principle_block' => ''
        ]
    );
} /* Nobody else should be here
 */
else {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

/* Event Types
 */
$allEventTypes = EventTypeMapper::getInstance()->findAll();

$page->dropDown(
    'type_drop',
    $allEventTypes,
    $defaultTypeId,
    TEMPLATE_content,
    [
        'id'   => 'EVENT_EVENTTYPE_ID',
        'type' => 'EVENT_EVENTTYPE_NAME'
    ]
);

/* Campus Checkbox Array when using Campus Event Level
 */
$campusList = CampusMapper::getInstance()->findAll();

if ($campusList->count()) {
    $allCampuses = [];

    foreach ($campusList as $current) {
        $allCampuses[] = [
            'id'   => $current->getId(),
            'name' => $current->getName()
        ];
    }

    $page->checkList(
        'event_campus_list',
        $allCampuses,
        $defaultCampusId,
        TEMPLATE_content,
        [
            'id'   => 'EVENT_CAMPUS_ID',
            'name' => 'EVENT_CAMPUS_NAME',
        ]
    );
} else {
    $page->setBlock(TEMPLATE_content, 'event_campus_list', 'event_campus_list_block');
    $page->setVar(
        [
            'event_campus_list_block' => ''
        ]
    );
}

/* Roles Checkbox Array
 */
$rolesList = RoleMapper::getInstance()->findAll();

if ($rolesList->count()) {
    $allRoles = [];

    foreach ($rolesList as $current) {
        $allRoles[] = [
            'id'   => $current->getId(),
            'name' => $current->getName()
        ];
    }

    $page->checkList(
        'event_role_list',
        $allRoles,
        $selectedRoles,
        TEMPLATE_content,
        [
            'id'   => 'EVENT_ROLE_ID',
            'name' => 'EVENT_ROLE_NAME',
        ]
    );
} else {
    $page->setVar(
        [
            'event_role_list_block' => ''
        ]
    );
}

// Legacy support for direct access to modify.php
if (!isset($this)) {
    $page->outputPage();
}
