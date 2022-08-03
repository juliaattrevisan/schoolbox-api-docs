<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/** @var SitePage $this */
$authenticator = new \EOTC\Authenticator($this);

$event = false;

if ($authenticator->isEOTCCreator($_SESSION['user'])) {
    $eventMapper = \EOTC\EventMapper::getInstance();

    try {
        $event = $eventMapper->findById($_POST['eventId']);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(160);
    }

    if (is_array($_POST['selected']) && count($_POST['selected'])) {
        \EOTC\EventMetadataMapper::getInstance()->deleteStudentAttendeesRelationsSpecific($event->getMetadata(), $_POST['selected']);
    }

    $this->localRedir('/modules/eotc/manage/attendance/list/' . $event->getId());
} else {
    error(19);
}
