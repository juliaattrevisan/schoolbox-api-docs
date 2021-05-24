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
    if (strlen($_POST['courseCode'])) {
        $externalHandler = new \External_Synergetic_Class();
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
