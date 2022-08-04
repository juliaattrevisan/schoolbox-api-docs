<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use EOTC\Authenticator;
use EOTC\ConfigMapper;
use EOTC\Event as EOTCEvent;
use EOTC\EventMapper as EOTCEventMapper;
use Schoolbox\Campus;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\RoleMapper;

/** @var SitePage $this */
$authenticator = new Authenticator($this);

/** @var EOTCEvent $event */
$event = null;

if ($authenticator->isEOTCCreator($_SESSION['user'])) {
    $eventMapper = EOTCEventMapper::getInstance();

    try {
        $event = $eventMapper->findById($this->arg[0]);
    } catch (MapperException $e) {
        error(ErrorMapper::ERROR_EVENT_NOT_FOUND);
    }

    $groupHandler = new Group();

    $this->setFile(TEMPLATE_content, 'modules/eotc/manage/attendance/list.html');
    $this->setBlock(TEMPLATE_content, 'student_attendee', 'student_attendee_block');
    $this->setBlock(TEMPLATE_content, 'filetype', 'filetype_block');

    $eventCampusCodes = [];

    /** @var Campus $campus */
    foreach ($event->getCampusCollection() as $campus) {
        $eventCampusCodes[] = $campus->getExtTimetableCode();
    }

    $this->setVar([
        'EVENT_ID' => $event->getId(),
        'EVENT_NAME' => $event->getTitle(),
        'EVENT_CAMPUS_CODES' => json_encode($eventCampusCodes),
        'STUDENT_GROUP_TYPE_IDS' => implode(';', $groupHandler->getStudentGroupTypeIds()),
    ]);

    // Retrieve file type blacklist from database and store in array

    $configMapper = ConfigMapper::getInstance();
    $allConfigs = $configMapper->findAll();
    $externalFiletypeBlacklist = $allConfigs->getBy('name', 'external_filetype_blacklist')->getValue();
    $externalFiletypeBlacklist = explode(",", $externalFiletypeBlacklist);

    // Iterate all file types from the database

    $this->setVar('filetype_block', '');

    $externalHandler = new External_Class();
    $allFiletypes = $externalHandler->findClassTypes();

    if (is_array($allFiletypes) && count($allFiletypes)) {
        foreach ($allFiletypes as $currentFiletype) {
            $fileType = strtoupper(trim($currentFiletype['Code']));

            // Ensure this file type is not in blacklist

            if (!in_array($fileType, $externalFiletypeBlacklist, false)) {
                // Was not in blacklist, add it to the dropdown

                $this->setVar([
                    'FILETYPE_ID' => $fileType,
                    'FILETYPE_NAME' => $currentFiletype['Description']
                ]);

                $this->parse('filetype_block', 'filetype', true);
            }
        }
    }


    if ($event->getMetadata()->getStudentAttendees()->count()) {
        $roleMapper = RoleMapper::getInstance();

        foreach ($event->getMetadata()->getStudentAttendees() as $currentStudent) {
            $role = $roleMapper->findById($currentStudent->getRole()->getId());
            $this->setVar([
                'STUDENT_ATTENDEE_ID' => $currentStudent->getId(),
                'STUDENT_ATTENDEE_FIRSTNAME' => $currentStudent->getFirstname(),
                'STUDENT_ATTENDEE_LASTNAME' => $currentStudent->getLastname(),
                'STUDENT_ATTENDEE_ROLE_NAME' => $role->getName(),
            ]);
            $this->parse('student_attendee_block', 'student_attendee', true);
        }
    } else {
        $this->setVar([
            'student_attendee_block' => ''
        ]);
    }
} else {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}
