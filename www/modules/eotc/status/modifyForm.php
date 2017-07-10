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

use Alaress\Database\Factory;
use EOTC\Excursion\Connector as ExcursionConnector;
use EOTC\ActivityLog\Registry as ActivityLogRegistry;
use EOTC\ActivityLog\Factory as ActivityLogFactory;
use EOTC\Authenticator;
use EOTC\EventMapper;
use EOTC\EventMetadataMapper;
use EOTC\EventStatusMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;

$page = new SitePage();
$event = false;

/* Spawn an existing event if id provided
 */
try {
	$eventMapper = EventMapper::getInstance();

	/** @var \EOTC\Event $event */
	$event = $eventMapper->findById($_POST['eventId']);
}
catch (MapperException $e) {
	error(160);
}

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

/* Only EOTC Administrators, those who can promote, and Subscribed Users
 * can view this page
 */
$authenticator = new Authenticator($page);

/* Only EOTC Administrators can view this page and a special case for
 * events where creators can self-promote this status into the next.
 */
$isEOTCAdministrator = $authenticator->isEOTCAdministrator($sessionUser, $event);
$isAuthor = (($event->getAuthor()->getId() == $sessionUser->getId()) && $authenticator->isEOTCCreator($sessionUser));
$isCanSelfPromote = $authenticator->canSelfPromote($sessionUser, $event);

/* All EOTC Administrators can alter Approved/Rejected events
 */
$isCanFinalise = $authenticator->canFinalise($sessionUser, $event);

/* Authors cannot view an event that is in Final Approval
 * Instead redirect to a different place
 *
 * Unless you're an EOTC administrator, then you can still
 * modify it (provided you've the appropriate permissions)
 */
if ($isAuthor && !$isEOTCAdministrator && $event->getMetadata()->getStatus()->isApproved()) {
	$page->localRedir("/modules/eotc/status/modify/{$event->getId()}");
}
/* EOTC Administrators and event authors can edit this event
 */
else if ($isEOTCAdministrator || $isCanSelfPromote || $isCanFinalise) {

	/* Remember original states so we know which obs to spawn
	 */
	$originalStatus = $event->getMetadata()->getStatus()->getId();
	$originalComment = $event->getMetadata()->getComment();

	/* Update with values
	 */
	$statusMapper = EventStatusMapper::getInstance();

	/** @var \EOTC\EventStatus $status */
	$status = $statusMapper->findById($_POST['statusId']);

	if (!$authenticator->isStatusChangeAllowed($event, $status))  {
		error(165);
	}

	$event->getMetadata()->setStatus($status);
	$event->getMetadata()->setComment($_POST['comment']);

	$metadataMapper = EventMetadataMapper::getInstance();
	$metadataMapper->save($event->getMetadata());

	/* Activity Log
	 */
	$registry = new ActivityLogRegistry();
	$factory = new ActivityLogFactory($event, $sessionUser);

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

    /* Sync to External DB only when the current status is Approved and
     * its previous status was not Approved so we can't spam this event
     * into External DB by altering its contents after the fact
     */
    if ($status->isApproved() && ($originalStatus != EventStatusMapper::STATUS_APPROVED)) {
        $configLoader = new ConfigLoader();

        /* Spin up a database factory
         */
        $factory = new Factory();

        /* Spawn the one we want
         */
        $db = $factory->createMssql();

        /* Connect to the place we want
         */
        $db->connect(
            $configLoader->get('external_db_host'),
            $configLoader->get('external_db_db'),
            $configLoader->get('external_db_user'),
            $configLoader->get('external_db_passwd')
        );

        $excursionConnector = new ExcursionConnector($db);
        $excursionConnector->sync($event);
    }

	/* Admin redirect back to review screen
	 */
	$page->localRedir('/modules/eotc/review/');
}
else {
	error(19);
}
