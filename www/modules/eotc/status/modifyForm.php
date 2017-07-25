<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$kernel = require_once(dirname(__FILE__) . '/../../../../library/Bootstrap.php');

use EOTC\Excursion\Connector as ExcursionConnector;
use EOTC\ActivityLog\Registry as ActivityLogRegistry;
use EOTC\ActivityLog\Factory as ActivityLogFactory;
use EOTC\Authenticator;
use EOTC\ConfigMapper;
use EOTC\Event;
use EOTC\EventMapper;
use EOTC\EventMetadataMapper;
use EOTC\EventStatus;
use EOTC\EventStatusMapper;
use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\Mapper\Exception as MapperException;

$page = new SitePage();
$page->setDi($kernel->getDependencyInjector());

$eventMapper = EventMapper::getInstance();

$event = false;

/* Spawn an existing event if id provided
 */
try {
	/** @var Event $event */
	$event = $eventMapper->findById($_POST['eventId']);
}
catch (MapperException $e) {
	error(160);
}

/** @var User $sessionUser */
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
elseif ($isEOTCAdministrator || $isCanSelfPromote || $isCanFinalise) {

	/* Remember original states so we know which obs to spawn
	 */
	$originalStatus = $event->getMetadata()->getStatus()->getId();
	$originalComment = $event->getMetadata()->getComment();

	/* Update with values
	 */
	$statusMapper = EventStatusMapper::getInstance();

	/** @var EventStatus $status */
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

	/* Optionally sync with external db
	 */
    $configMapper = new ConfigMapper();
    $externalDbSync = $configMapper->findOneByColumn('name', 'external_db_sync');

    if ($externalDbSync->getValue() == 1) {

        /* Sync to External DB only when:
         * - the current status is Approved and
         * - its previous status was not Approved
         * - Permission Slip is required
         *
         * We need to check the previous status so we don't spam this event
         * into External DB by altering its contents after it was already approved
         */
        if ($status->isApproved() && $event->getMetadata()->getPermissionSlipRequired() && ($originalStatus != EventStatusMapper::STATUS_APPROVED)) {
            $excursionConnector = new ExcursionConnector($kernel->get('config')->get('external_type'), $kernel->get('databaseExternal'));

            /* Synchronise all events in a series if appropriate
             * Otherwise just sync one event
             */
            if ($event->getSeriesId()) {
                $allEvents = $eventMapper->getAllInSeries($event);
            }
            else {
                $allEvents = new Collection();
                $allEvents->add($event);
            }

            /** @var Event $currentEvent */
            foreach ($allEvents as $currentEvent) {
                try {
                    $excursionConnector->sync($currentEvent);
                }
                catch (\DomainException $e) {

                    /* Occurs when trying to create a new EOTC event, but a Synweb id
                     * already exists for it. This can happen if
                     *  - you mangle the Back button in the browser
                     *  - or if superusers change status to non-approved then re-approve
                     */
                }
            }
        }
    }

	/* Admin redirect back to review screen
	 */
	$page->localRedir('/modules/eotc/review/');
}
else {
	error(19);
}
