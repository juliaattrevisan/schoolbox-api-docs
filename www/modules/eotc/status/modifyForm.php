<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../../../library/Bootstrap.php');

$page = new SitePage();
$event = false;

/* Spawn an existing event if id provided
 */
try {
	$eventMapper = \EOTC\EventMapper::getInstance();
	$event = $eventMapper->findById($_POST['eventId']);
}
catch (\Schoolbox\Mapper\Exception $e) {
	error(160);
}

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

/* Only EOTC Administrators, those who can promote, and Subscribed Users
 * can view this page
 */
$authenticator = new \EOTC\Authenticator();

/* Only EOTC Administrators can view this page and a special case for
 * events where creators can self-promote this status into the next.
 */
$isEOTCAdministrator = $authenticator->isEOTCAdministrator($sessionUser, $event);
$isCanSelfPromote = $authenticator->canSelfPromote($sessionUser, $event);

/* All EOTC Administrators can alter Approved/Rejected events
 */
$isCanFinalise = $authenticator->canFinalise($sessionUser, $event);

if ($isEOTCAdministrator || $isCanSelfPromote || $isCanFinalise) {

	/* Remember original states so we know which obs to spawn
	 */
	$originalStatus = $event->getMetadata()->getStatus()->getId();
	$originalComment = $event->getMetadata()->getComment();

	/* Update with values
	 */
	$statusMapper = \EOTC\EventStatusMapper::getInstance();

	/** @var \EOTC\EventStatus $status */
	$status = $statusMapper->findById($_POST['statusId']);

	if (!$authenticator->isStatusChangeAllowed($event, $status))  {
		error(165);
	}

	$event->getMetadata()->setStatus($status);
	$event->getMetadata()->setComment($_POST['comment']);

	$metadataMapper = \EOTC\EventMetadataMapper::getInstance();
	$metadataMapper->save($event->getMetadata());

	/* Activity Log
	 */
	$registry = new \EOTC\ActivityLog\Registry();
	$factory = new \EOTC\ActivityLog\Factory($event, $sessionUser);

	/* If a status changed, log it
	 */
	if ($originalStatus != $event->getMetadata()->getStatus()->getId()) {
		$registry->add($factory->produce('StatusChange'));
	}

	/* If a comment changed, log it
	 */
	if ($originalComment != $event->getMetadata()->getComment()) {
		$registry->add($factory->produce('CommentChange'));
	}

	/* Notify everyone
	 */
	$registry->notify();

	/* Admin redirect back to review screen
	 */
	$page->localRedir('/modules/eotc/review/');
}
else {
	error(19);
}
