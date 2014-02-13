<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../../../include/Bootstrap.php');

$page = new SitePage();

$eventMapper = new \EOTC\EventMapper();

/* Spawn an existing event if id provided
 * Otherwise spawn a new one
 */
try {
	$event = $eventMapper->findById($_POST['eventId']);
}
catch (\Exception $e) {
	error(160);
}

$authenticator = new \EOTC\Authenticator($_SESSION['user'], $event);

if ($authenticator->canPromote()) {

	/* Remember original states so we know which obs to spawn
	 */
	$originalStatus = $event->getMetadata()->getStatus()->getId();
	$originalComment = $event->getMetadata()->getComment();

	/* Update with values
	 */
	$statusMapper = new \EOTC\EventStatusMapper();
	$status = $statusMapper->findById($_POST['statusId']);

	$event->getMetadata()->setStatus($status);
	$event->getMetadata()->setComment($_POST['comment']);

	$metadataMapper = new \EOTC\EventMetadataMapper();
	$metadataMapper->save($event->getMetadata());

	/* Activity Log
	 */
	$registry = new \EOTC\ActivityLog\Registry();
	$factory = new \EOTC\ActivityLog\Factory($event, $_SESSION['user']);

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

	/* Redirect back to status screen
	 */
	$page->localRedir('/modules/eotc/status/modify/' . $event->getId());
}
else {
	error(19);
}