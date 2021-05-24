<?php

/**
 * Attendance List
 *
 * A read-only version of list.php which shows you who is attending
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use EOTC\Authenticator;
use EOTC\EventMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\RoleMapper;
use Schoolbox\User;

$event = false;

try {
    $eventMapper = EventMapper::getInstance();
    $event = $eventMapper->findById($this->arg[0]);
} catch (MapperException $e) {
    error(ErrorMapper::ERROR_EVENT_NOT_FOUND);
}

/** @var User $sessionUser */
$sessionUser = $_SESSION['user'];

/* Only EOTC Administrators and Subscribed Users can view this page
 */
$authenticator = new Authenticator($this);

$isEOTCAdministrator = $authenticator->isEOTCAdministrator($sessionUser, $event);
$isSubscribedUser = $authenticator->isSubscribedUser($sessionUser, $event);

if ($isEOTCAdministrator || $isSubscribedUser) {
    $this->setFile(TEMPLATE_content, 'modules/eotc/status/attendance.html');
    $this->setBlock(TEMPLATE_content, 'has_staff_attendees', 'has_staff_attendees_block');
    $this->setBlock('has_staff_attendees', 'staff_attendee', 'staff_attendee_block');
    $this->setBlock(TEMPLATE_content, 'no_staff_attendees', 'no_staff_attendees_block');
    $this->setBlock(TEMPLATE_content, 'has_student_attendees', 'has_student_attendees_block');
    $this->setBlock('has_student_attendees', 'student_attendee', 'student_attendee_block');
    $this->setBlock(TEMPLATE_content, 'no_student_attendees', 'no_student_attendees_block');

    $this->setVar([
        'EVENT_ID' => $event->getId(),
        'EVENT_NAME' => $event->getTitle(),
    ]);

    $roleMapper = RoleMapper::getInstance();

    /* If there are staff, list them, otherwise show empty state
     */
    if ($event->getMetadata()->getStaffAttendees()->count()) {
        foreach ($event->getMetadata()->getStaffAttendees() as $currentStaff) {
            $role = $roleMapper->findById($currentStaff->getRole()->getId());

            $this->setVar([
                'STAFF_ATTENDEE_EXTERNAL_ID' => $currentStaff->getExternalId(),
                'STAFF_ATTENDEE_ID' => $currentStaff->getId(),
                'STAFF_ATTENDEE_FIRSTNAME' => $currentStaff->getFirstname(),
                'STAFF_ATTENDEE_LASTNAME' => $currentStaff->getLastname(),
                'STAFF_ATTENDEE_ROLE_NAME' => $role->getName(),
            ]);

            $this->parse('staff_attendee_block', 'staff_attendee', true);
        }

        $this->setVar([
            'no_staff_attendees_block' => ''
        ]);

        $this->parse('has_staff_attendees_block', 'has_staff_attendees');
    } else {
        $this->setVar([
            'has_staff_attendees_block' => '',
            'staff_attendee_block' => ''
        ]);

        $this->parse('no_staff_attendees_block', 'no_staff_attendees');
    }

    /* If there are students, list them, otherwise show empty state
     */
    if ($event->getMetadata()->getStudentAttendees()->count()) {
        foreach ($event->getMetadata()->getStudentAttendees() as $currentStudent) {
            $role = $roleMapper->findById($currentStudent->getRole()->getId());

            $this->setVar([
                'STUDENT_ATTENDEE_EXTERNAL_ID' => $currentStudent->getExternalId(),
                'STUDENT_ATTENDEE_ID' => $currentStudent->getId(),
                'STUDENT_ATTENDEE_FIRSTNAME' => $currentStudent->getFirstname(),
                'STUDENT_ATTENDEE_LASTNAME' => $currentStudent->getLastname(),
                'STUDENT_ATTENDEE_ROLE_NAME' => $role->getName(),
            ]);

            $this->parse('student_attendee_block', 'student_attendee', true);
        }

        $this->setVar([
            'no_student_attendees_block' => ''
        ]);

        $this->parse('has_student_attendees_block', 'has_student_attendees');
    } else {
        $this->setVar([
            'has_student_attendees_block' => '',
            'student_attendee_block' => ''
        ]);

        $this->parse('no_student_attendees_block', 'no_student_attendees');
    }
} else {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}
