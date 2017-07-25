<?php
/**
 * Attendance List CSV
 *
 * A read-only version of list.php in csv format
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use EOTC\Authenticator;
use EOTC\EventMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\RoleMapper;
use Schoolbox\User;

$event = false;

try {
	$eventMapper = EventMapper::getInstance();
	$event = $eventMapper->findById($this->arg[0]);
}
catch (MapperException $e) {
	error(160);
}

/** @var User $sessionUser */
$sessionUser = $_SESSION['user'];

/* Only EOTC Administrators and Subscribed Users can view this page
 */
$authenticator = new Authenticator($this);

$isEOTCAdministrator = $authenticator->isEOTCAdministrator($sessionUser, $event);
$isSubscribedUser = $authenticator->isSubscribedUser($sessionUser, $event);

if ($isEOTCAdministrator || $isSubscribedUser) {
    $roleMapper = RoleMapper::getInstance();
	$csv = '';

	$this->setVar([
		'EVENT_ID' => $event->getId(),
		'EVENT_NAME' => $event->getTitle(),
	]);

    $row = [
        'External ID',
        'Firstname',
        'Surname',
        'Group'
    ];

    $csv .= TextFormatter::outputCSV($row) . "\n";

    /* A list of everyone
     */
    /** @var Collection $everyone */
    $everyone = new Collection();
    $everyone = $everyone->merge($event->getMetadata()->getStaffAttendees());
    $everyone = $everyone->merge($event->getMetadata()->getStudentAttendees());

    if ($everyone->count()) {
        foreach ($everyone as $current) {
            $role = $roleMapper->findById($current->getRole()->getId());

            $row = [
                $current->getExternalId(),
                $current->getFirstname(),
                $current->getLastname(),
                $role->getName(),
            ];

            $csv .= TextFormatter::outputCSV($row) . "\n";
        }
    }

	/* Thump out a csv
     */
	$slug = new \Url("{$event->getId()}-{$event->getTitle()}-attendees");
	$filename = $slug->getSlug();

	header('Content-type: text/ascii');
	header("Content-disposition: attachment; filename=\"{$filename}.csv\"");
	echo $csv;

	exit();
}
else {
	error(19);
}

