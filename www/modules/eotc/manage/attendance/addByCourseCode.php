<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

$authenticator = new \EOTC\Authenticator($this);

$event = false;

if ($authenticator->isEOTCCreator($_SESSION['user'])) {
    $eventMapper = \EOTC\EventMapper::getInstance();

    try {
        $event = $eventMapper->findById($_POST['eventId']);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(160);
    }

    /* If a course code was provided, find all the students in it
     */
    if (strlen($_POST['courseCode'] ?? '')) {
        /* This only works with the Synergetic connector.
         * An alternative to 404 would be errors:
         *  169 Access to this module is denied
         *  19 You do not have permission to view this page.
         *
         * ...but none of those really fit the context as to why you'd be unable to access this place.
         */
        if (\External\IConnector::EXTERNAL_TYPE_SYNERGETIC !== Config::get('external_type')) {
            error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
        }

        $externalHandler = new External_Class();
        $allStudents = $externalHandler->getStudents($_POST['courseCode']);

        if (is_array($allStudents) && count($allStudents)) {
            $userMapper = \Schoolbox\UserMapper::getInstance();
            $ids = array();

            foreach ($allStudents as $currentStudent) {
                try {
                    $user = $userMapper->findByExternalId($currentStudent['synergy_id']);
                    $ids[] = $user->getId();
                } catch (\Schoolbox\Mapper\Exception $e) {
                }
            }

            \EOTC\EventMetadataMapper::getInstance()->addStudentAttendeesRelationsSpecific($event->getMetadata(), $ids);
        }
    }

    $this->localRedir('/modules/eotc/manage/attendance/list/' . $event->getId());
} else {
    error(19);
}
