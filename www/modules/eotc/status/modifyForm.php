<?php

/**
 * Update an event's status
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

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
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\User;
use Schoolbox\Mapper\Exception as MapperException;

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

$eventMapper = EventMapper::getInstance();

$event = false;

/* Spawn an existing event if id provided
 */
try {
    /** @var Event $event */
    $event = $eventMapper->findById($_POST['eventId']);
} catch (MapperException $e) {
    error(ErrorMapper::ERROR_EVENT_NOT_FOUND);
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
} /* EOTC Administrators and event authors can edit this event
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

    if (!$authenticator->isStatusChangeAllowed($event, $status)) {
        $l10n = $page->getDi()->get('l10n');
        $errorMessage = ErrorMapper::getInstance()->getMessageById(ErrorMapper::ERROR_EOTC_EVENT_NO_STUDENTS);

        throw new SchoolboxException($l10n->translateSentence($errorMessage));
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
    $externalDb = $kernel->get('databaseExternal');

    if ((int)$externalDbSync->getValue() == 1 && $externalDb instanceof \Alaress\Database\Connector\IConnector) {
        /* Sync to External DB only when:
         * - the current status is Approved and
         * - its previous status was not Approved
         * - Permission Slip is required
         *
         * We need to check the previous status so we don't spam this event
         * into External DB by altering its contents after it was already approved
         */
        if ($status->isApproved() && $event->getMetadata()->getPermissionSlipRequired() && ($originalStatus != EventStatusMapper::STATUS_APPROVED)) {
            $excursionConnector = new ExcursionConnector(
                $kernel->get('config')->get('external_type'),
                $externalDb,
                $configMapper->findOneByColumn('name', 'external_eventtype')->getValue()
            );

            /* Synchronise all events in a series if appropriate
             * Otherwise just sync one event
             */
            if ($event->getSeriesId()) {
                $allEvents = $eventMapper->getAllInSeries($event);
            } else {
                $allEvents = new Collection();
                $allEvents->add($event);
            }

            /** @var Event $currentEvent */
            foreach ($allEvents as $currentEvent) {
                try {
                    $excursionConnector->sync($currentEvent);
                } catch (\DomainException $e) {
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
} else {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}
