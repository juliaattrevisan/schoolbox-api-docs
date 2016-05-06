<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$authenticator = new \EOTC\Authenticator();

$event = false;

if ($authenticator->isEOTCCreator($_SESSION['user'])) {
    $eventMapper = \EOTC\EventMapper::getInstance();

    try {
        $event = $eventMapper->findById($this->arg[0]);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(160);
    }

    $groupHandler = new Group();

    $this->setFile(TEMPLATE_content, 'modules/eotc/manage/attendance/list.html');
    $this->setBlock(TEMPLATE_content, 'student_attendee', 'student_attendee_block');
    $this->setBlock(TEMPLATE_content, 'filetype', 'filetype_block');

    $eventCampusCodes = array();

    foreach ($event->getCampusCollection() as $campus) {
        $eventCampusCodes[] = $campus->getExtTimetableCode();
    }

    $this->setVar(
        array(
            'EVENT_ID'               => $event->getId(),
            'EVENT_NAME'             => $event->getTitle(),
            'EVENT_CAMPUS_CODES'     => json_encode($eventCampusCodes),
            'STUDENT_GROUP_TYPE_IDS' => implode(';', $groupHandler->getStudentGroupTypeIds()),
        )
    );

    $externalHandler = new \External_Synergetic_Class();
    $allFiletypes    = $externalHandler->findFiletypes();

    // Lame hardcoded filetype lameness. LAAAAAME [SB-5494]
    $hardcodedFiletypes = array(
        'A' => 'Academic',
        'C' => 'Cultural',
        'L' => 'Leadership',
        'M' => 'Music',
        'R' => 'Service',
        'S' => 'Sports',
        'V' => 'Awards',
        'Z' => 'Miscellaneous',
    );

    $this->setVar('filetype_block', '');

    if (is_array($allFiletypes) && count($allFiletypes)) {
        foreach ($allFiletypes as $currentFiletype) {
            if (array_key_exists($currentFiletype['Code'], $hardcodedFiletypes)) {
                $this->setVar(
                    array(
                        'FILETYPE_ID'   => $currentFiletype['Code'],
                        'FILETYPE_NAME' => $currentFiletype['Description']
                    )
                );

                $this->parse('filetype_block', 'filetype', true);
            }
        }
    }

    if ($event->getMetadata()->getStudentAttendees()->count()) {
        $roleMapper = \Schoolbox\RoleMapper::getInstance();

        foreach ($event->getMetadata()->getStudentAttendees() as $currentStudent) {
            $role = $roleMapper->findById($currentStudent->getRole()->getId());
            $this->setVar(
                array(
                    'STUDENT_ATTENDEE_ID'        => $currentStudent->getId(),
                    'STUDENT_ATTENDEE_FIRSTNAME' => $currentStudent->getFirstname(),
                    'STUDENT_ATTENDEE_LASTNAME'  => $currentStudent->getLastname(),
                    'STUDENT_ATTENDEE_ROLE_NAME' => $role->getName(),
                )
            );
            $this->parse('student_attendee_block', 'student_attendee', true);
        }
    } else {
        $this->setVar(
            array(
                'student_attendee_block' => ''
            )
        );
    }
} else {
    error(19);
}
