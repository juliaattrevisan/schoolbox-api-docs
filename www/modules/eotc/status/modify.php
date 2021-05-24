<?php

/**
 * Status Modify
 *
 * Allows you to see the current status of an EOTC Event, such as
 * its details. This is -not- the EOTC Event modify screen which
 * allows you to change those details.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use EOTC\Authenticator;
use EOTC\ConfigMapper;
use EOTC\EventMapper;
use Schoolbox\Calendar\EventRecurrenceMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;

$event = false;

try {
    $event = EventMapper::getInstance()->findById($this->arg[0]);
} catch (MapperException $e) {
    error(ErrorMapper::ERROR_EVENT_NOT_FOUND);
}

/** @var User $sessionUser */
$sessionUser = $_SESSION['user'];

/* Only EOTC Administrators, those who can promote, and Subscribed Users
 * can view this page
 */
$authenticator = new Authenticator($this);

$isEOTCAdministrator = $authenticator->isEOTCAdministrator($sessionUser, $event);
$isCanSelfPromote    = $authenticator->canSelfPromote($sessionUser, $event);
$isSubscribedUser    = $authenticator->isSubscribedUser($sessionUser, $event);
$isAuthor            = (($event->getAuthor()->getId() == $sessionUser->getId()) && $authenticator->isEOTCCreator($sessionUser));

/* All EOTC Administrators can see Approved/Rejected events
 */
$isCanFinalise = $authenticator->canFinalise($sessionUser, $event);

if ($isEOTCAdministrator || $isCanSelfPromote || $isSubscribedUser || $isCanFinalise) {
    $this->setFile(TEMPLATE_content, 'modules/eotc/status/modify.html');
    $this->setBlock(TEMPLATE_content, 'event_campus', 'event_campus_block');
    $this->setBlock(TEMPLATE_content, 'has_rams', 'has_rams_block');
    $this->setBlock('has_rams', 'rams_list', 'rams_list_block');
    $this->setBlock(TEMPLATE_content, 'no_rams', 'no_rams_block');
    $this->setBlock(TEMPLATE_content, 'has_event_activity_log', 'has_event_activity_log_block');
    $this->setBlock('has_event_activity_log', 'event_activity_log', 'event_activity_log_block');
    $this->setBlock(TEMPLATE_content, 'has_change_event_status', 'has_change_event_status_block');
    $this->setBlock('has_change_event_status', 'event_status', 'event_status_block');
    $this->setBlock(TEMPLATE_content, 'recurring_reminder', 'recurring_reminder_block');
    $this->setBlock(TEMPLATE_content, 'event_resourcebooking', 'event_resourcebooking_block');
    $this->setBlock(TEMPLATE_content, 'has_modify_event', 'has_modify_event_block');
    $this->setBlock(TEMPLATE_content, 'teacher_in_charge_link_start', 'teacher_in_charge_link_start_block');
    $this->setBlock(TEMPLATE_content, 'teacher_in_charge_link_end', 'teacher_in_charge_link_end_block');
    $this->setBlock(TEMPLATE_content, 'author_link_start', 'author_link_start_block');
    $this->setBlock(TEMPLATE_content, 'author_link_end', 'author_link_end_block');

    /* Find out if the external sync config is enabled
     * Disable when not in db for some magical reason
     */
    try {
        $syncConfig = ConfigMapper::getInstance()->findOneByColumn('name', 'external_db_sync')->getValue();
    } catch (MapperException $e) {
        $syncConfig = false;
    }

    $this->setVar([
        'SYNC_ENABLED' => $syncConfig == 1 ? 'true' : 'false'
    ]);

    /**
     * If the teacher in charge has been deleted, do not add hyperlink
     */
    if ($event->getTeacherInCharge()->isDeleted()) {
        $this->setVar('teacher_in_charge_link_start_block', '');
        $this->setVar('teacher_in_charge_link_end_block', '');
    } else {
        $this->parse('teacher_in_charge_link_start_block', 'teacher_in_charge_link_start');
        $this->parse('teacher_in_charge_link_end_block', 'teacher_in_charge_link_end');
    }

    /**
     * If the author has been deleted, do not add hyperlink
     */
    if ($event->getAuthor()->isDeleted()) {
        $this->setVar('author_link_start_block', '');
        $this->setVar('author_link_end_block', '');
    } else {
        $this->parse('author_link_start_block', 'author_link_start');
        $this->parse('author_link_end_block', 'author_link_end');
    }

    /* Event details
     */
    $this->setVar(
        [
            'EVENT_ID'           => $event->getId(),
            'EVENT_TITLE'        => $event->getTitle(),
            'EVENT_LOCATION'     => $event->getLocation(),
            'EVENT_DETAIL'       => $event->getDetail(),
            'EVENT_CREATED_AT'   => $event->getMetadata()->getCreatedAt()->getShort(),
            'EVENT_DATESTAMP'    => $event->getStartDateTime()->getURLDate(),
            'EVENT_START'        => $event->getStartDateTime()->getShort(),
            'EVENT_END'          => $event->getEndDateTime()->getShort(),
            'EVENT_STATUS'       => $event->getMetadata()->getStatus()->getName(),
            'EVENT_STATUS_CLASS' => $event->getMetadata()->getStatus()->getStyle(),
        ]
    );

    /* Recurrence
     */
    // Is there a recurrence? If so don't show the repeat selector.
    $recurrence = [];

    if ($event->getSeriesId()) {
        $eventRecurrenceMapper = new EventRecurrenceMapper(EventMapper::getInstance());
        $recurrence            = $eventRecurrenceMapper->getSeriesRecurrence($event);
    }

    if (!empty($recurrence)) {

        /** @var \Alaress_Date_Format $recurrenceStart */
        /** @var \Alaress_Date_Format $recurrenceEnd */
        $recurrenceStart = $recurrence['start'];
        $recurrenceEnd   = $recurrence['end'];

        $this->setVar(
            [
                'SERIES_EVENTS_NUM'         => $recurrence['num'],
                'SERIES_EVENTS_DESCRIPTION' => $recurrence['freq'],
                'EVENT_REC_FREQ'            => ucfirst($recurrence['freq']),
                'EVENT_REC_START'           => $recurrenceStart->getLongDateOnly(),
                'EVENT_REC_END'             => $recurrenceEnd->getLongDateOnly(),
            ]
        );

        $this->parse('recurring_reminder_block', 'recurring_reminder');
    } else {
        $this->setVar(
            [
                'SERIES_EVENTS_NUM'         => 0,
                'SERIES_EVENTS_DESCRIPTION' => '',
                'EVENT_REC_FREQ'            => '',
                'EVENT_REC_START'           => '',
                'EVENT_REC_END'             => '',
                'recurring_reminder_block'  => ''
            ]
        );
    }

    /* RAMS file requirement
     */
    $this->setVar(
        [
            'has_rams_block'  => '',
            'rams_list_block' => '',
        ]
    );

    if ($event->getMetadata()->getRamsRequired()) {
        /* Sometimes event/file relations get recorded with a storage_id of 0
         * so we need to check whether one actually legitimately saved properly
         */
        foreach ($event->getMetadata()->getRamsFiles() as $ramsFile) {
            $mimetype = MimetypeMapper::getInstance()->getByFilename($ramsFile->getBasename());

            $this->setVar(
                [
                    'EVENT_RAMS_FILE'              => $ramsFile->getBasename(),
                    'EVENT_RAMS_FILESIZE'          => $ramsFile->getSize(),
                    'EVENT_RAMS_MIMETYPE_ICONFILE' => $mimetype->getIconFile(),
                    'EVENT_RAMS_HASH'              => $ramsFile->getHash(),
                ]
            );

            $this->parse('rams_list_block', 'rams_list', true);
        }

        $this->parse('has_rams_block', 'has_rams');

        $this->setVar(
            [
                'no_rams_block' => '',
            ]
        );
    } else {
        $this->setVar(
            [
                'has_rams_block' => '',
            ]
        );

        $this->parse('no_rams_block', 'no_rams');
    }

    /* Teacher In Charge
     */
    $this->setVar(
        [
            'EVENT_TEACHER_IN_CHARGE_ID'          => $event->getTeacherInCharge()->getId(),
            'EVENT_TEACHER_IN_CHARGE_EXTERNAL_ID' => $event->getTeacherInCharge()->getExternalId(),
            'EVENT_TEACHER_IN_CHARGE_NAME'        => $event->getTeacherInCharge()->getFullname(),
        ]
    );

    /* Author
     */
    $this->setVar(
        [
            'EVENT_AUTHOR_ID'   => $event->getAuthor()->getId(),
            'EVENT_AUTHOR_NAME' => $event->getAuthor()->getFullname(),
        ]
    );

    /* Staff Attendees
     */
    $this->setVar(
        [
            'event_staff_block' => '',
            'EVENT_STAFF_COUNT' => $event->getMetadata()->getStaffAttendees()->count()
        ]
    );

    /* Student Attendees
     */
    $this->setVar(
        [
            'EVENT_STUDENT_COUNT' => $event->getMetadata()->getStudentAttendees()->count()
        ]
    );

    /* Related campuses
     */
    $this->setVar(
        [
            'event_campus_block' => ''
        ]
    );

    foreach ($event->getCampusCollection() as $currentCampus) {
        $this->setVar(
            [
                'EVENT_CAMPUS_NAME' => $currentCampus->getName()
            ]
        );

        $this->parse('event_campus_block', 'event_campus', true);
    }

    /* Resources
     */
    $this->setVar(
        [
            'EVENT_RESOURCE_COUNT'        => $event->getMetadata()->getResourceBookings()->count(),
            'event_resourcebooking_block' => ''
        ]
    );

    if ($event->getMetadata()->getResourceBookings()->count()) {
        $resourceMapper        = \Schoolbox\ResourceBooking\Item\Mapper::getInstance();
        $resourceBookingMapper = \Schoolbox\ResourceBooking\Booking\Mapper::getInstance();

        /* Rely on the cache :3
         */
        foreach ($event->getMetadata()->getResourceBookings() as $currentResourceBooking) {
            $resource        = $resourceMapper->findById($currentResourceBooking->getResource()->getId());
            $resourceBooking = $resourceBookingMapper->findById($currentResourceBooking->getId());

            $this->setVar(
                [
                    'EVENT_RESOURCEBOOKING_ID'    => $resourceBooking->getId(),
                    'EVENT_RESOURCEBOOKING_NAME'  => $resourceBooking->getName(),
                    'EVENT_RESOURCEBOOKING_START' => $resourceBooking->getEvent()->getStartDateTime()->getShort(),
                    'EVENT_RESOURCEBOOKING_END'   => $resourceBooking->getEvent()->getEndDateTime()->getShort(),
                    'EVENT_RESOURCE_ID'           => $resource->getId(),
                    'EVENT_RESOURCE_NAME'         => $resource->getName()
                ]
            );

            $this->parse('event_resourcebooking_block', 'event_resourcebooking', true);
        }
    }

    /* Activity Log
     */
    $this->setVar(
        [
            'event_activity_log_block'     => '',
            'has_event_activity_log_block' => ''
        ]
    );

    $i = 0;

    foreach ($event->getMetadata()->getActivityLog() as $currentLog) {
        /* If there are no activityLog items, mappers will spawn a single
         * object where every property is null
         *
         * TODO: Mappers probably shouldn't spawn you such objects
         */
        if ($currentLog->getId()) {
            $this->setVar(
                [
                    'EVENT_ACTIVITY_LOG_CREATED_AT' => $currentLog->getCreatedAt()->getShort(),
                    'EVENT_ACTIVITY_LOG_MESSAGE'    => $currentLog->getMessage()
                ]
            );

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


    /* Modify Event button
     * Special case for authors of approved events who aren't eotc admins
     */
    if ($isAuthor && !$isEOTCAdministrator && $event->getMetadata()->getStatus()->isApproved()) {
        $this->setVar(
            [
                'has_modify_event_block' => ''
            ]
        );
    } else {
        $this->parse('has_modify_event_block', 'has_modify_event');
    }

    /* Status section
     *
     * If you don't have permissions to the status of the event, you don't get
     * access to this section anymore
     */
    $this->setVar(
        [
            'has_change_event_status_block' => ''
        ]
    );

    /* Authors cannot view an event that is in Final Approval
     * Instead redirect to a different place
     *
     * Unless you're an EOTC administrator, then you can still
     * modify it (provided you've the appropriate permissions)
     */
    if ($isAuthor && !$isEOTCAdministrator && $event->getMetadata()->getStatus()->isApproved()) {
        /* noop
         */
    } else {
        /* Status Comment
         */
        $this->setVar(
            [
                'EVENT_COMMENT' => $event->getMetadata()->getComment(),
            ]
        );

        if ($isEOTCAdministrator || $isCanSelfPromote || $isCanFinalise) {
            $eventStatusMapper = \EOTC\EventStatusMapper::getInstance();

            /* Soops have access to all statuses
             */
            if ($sessionUser->isSuperuser()) {
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
                $this->setVar(
                    [
                        'EVENT_STATUS_ID'       => $currentStatus->getId(),
                        'EVENT_STATUS_NAME'     => $currentStatus->getName(),
                        'EVENT_STATUS_SELECTED' => '',
                    ]
                );

                /* Preselect
                 */
                if ($currentStatus->getId() == $event->getMetadata()->getStatus()->getId()) {
                    $this->setVar(
                        [
                            'EVENT_STATUS_SELECTED' => 'selected="selected"',
                        ]
                    );
                }

                $this->parse('event_status_block', 'event_status', true);
            }

            $this->parse('has_change_event_status_block', 'has_change_event_status');
        }
    }
} else {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}
