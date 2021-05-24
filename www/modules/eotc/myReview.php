<?php

/**
 * My Review
 *
 * A list of all EOTC events that $sessionUser created
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

new \EOTC\Authenticator($this);

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

if ($sessionUser->getRole()->isStaff() || $sessionUser->isSuperuser()) {
    $this->setFile(TEMPLATE_content, 'modules/eotc/myReview.html');
    $this->setBlock(TEMPLATE_content, 'event', 'event_block');
    $this->setBlock('event', 'link_start', 'link_start_block');
    $this->setBlock('event', 'link_end', 'link_end_block');

    $this->setVar([
        'event_block' => '',
    ]);

    $eventMapper = \EOTC\EventMapper::getInstance();
    $allEvents   = $eventMapper->findAllByListGroupsForUser($sessionUser);

    /* using foreach() won't increment the $position, which I use to get the name
     * of the collection currently being iterated
     */
    /**
     * @var \EOTC\Event $currentEvent
     */
    foreach ($allEvents as $currentEvent) {
        $this->setVar([
            'EVENT_ID'                         => $currentEvent->getId(),
            'EVENT_STATUS_NAME'                => $currentEvent->getMetadata()->getStatus()->getName(),
            'EVENT_TITLE'                      => $currentEvent->getTitle(),
            'EVENT_START_DATETIME'             => $currentEvent->getStartDatetime()->getShort(),
            'EVENT_TEACHER_IN_CHARGE_ID'       => $currentEvent->getTeacherInCharge()->getId(),
            'EVENT_TEACHER_IN_CHARGE_FULLNAME' => $currentEvent->getTeacherInCharge()->getFullName(),
            'EVENT_CREATED_AT'                 => $currentEvent->getMetadata()->getCreatedAt()->getShort(),
            'EVENT_STUDENTS'                   => $currentEvent->getMetadata()->getStudentAttendees()->count(),
            'EVENT_RAMS_REQUIRED'              => $currentEvent->getMetadata()->getRamsRequired() ? 'Y' : 'N',
        ]);

        if ($currentEvent->getTeacherInCharge()->isDeleted()) {
            $this->setVar('link_start_block', '');
            $this->setVar('link_end_block', '');
        } else {
            $this->parse('link_start_block', 'link_start');
            $this->parse('link_end_block', 'link_end');
        }

        $this->parse('event_block', 'event', true);
    }
} else {
    error(19);
}
