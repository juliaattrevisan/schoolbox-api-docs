<?php

/**
 * Persist event data to the dbs
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use EOTC\ActivityLog\Registry as ActivityLogRegistry;
use EOTC\ActivityLog\Factory as ActivityLogFactory;
use EOTC\Authenticator;
use EOTC\EventMapper;
use EOTC\EventMetadataMapper;
use EOTC\EventRecurrenceMapper;
use EOTC\EventStatus;
use EOTC\EventStatusMapper;
use Schoolbox\Collection;
use Schoolbox\Calendar\EventRelations;
use Schoolbox\Calendar\EventType;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\User;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

$authenticator = new Authenticator($page);

if ($authenticator->isEOTCCreator($_SESSION['user'])) {
    $userMapper = UserMapper::getInstance();

    /** @var User $user */
    $owner = $_SESSION['user'];

    /* Default
     */
    $until = null;

    $eventMapper = EventMapper::getInstance();

    /* Spawn an existing event if id provided
     * Otherwise spawn a new one
     */
    if (is_numeric($_POST['id'])) {
        try {
            $event = $eventMapper->findById($_POST['id']);
        } catch (MapperException $e) {
            error(ErrorMapper::ERROR_EVENT_NOT_FOUND);
            exit;
        }
    } else {
        $event = $eventMapper->createModel();
    }

    /* Spawn and assign metadata record to event
     */
    try {
        $metadataMapper = EventMetadataMapper::getInstance();
        $metadata       = $metadataMapper->findByEvent($event);
    } catch (MapperException $e) {
        $metadata       = EventMetadataMapper::getInstance()->createModel();
        $metadataStatus = EventStatusMapper::getInstance()->createModel();
        $metadataStatus->setId(EventStatusMapper::STATUS_DEFAULT);
        $metadata->setStatus($metadataStatus);
    }

    /* Cannot submit modification data if event is Approved and you
     * are its author, and you haven't got the Approved permission
     */
    if ($_POST['action'] == 'Update') {
        $isAuthor = (($event->getAuthor()->getId() == $owner->getId()) && $authenticator->isEOTCCreator($owner));
        $canAdminister = $authenticator->isEOTCAdministrator($owner, $event);

        /** @var \EOTC\Event $event **/
        if ($isAuthor && !$canAdminister && $event->getMetadata()->getStatus()->isApproved()) {
            $page->localRedir("/modules/eotc/status/modify/{$event->getId()}");
        }
    }

    /* NO RETREAT
     * NO SURRENDER
     */

    /* Author
     */
    try {
        /** @var User $author */
        $author = $userMapper->findById($_POST['authorId']);
    } catch (MapperException\Deleted $e) {
        // Allow a soft-deleted author to remain
        /** @var User $author */
        $author = $e->getDeletedObject();
    } catch (MapperException $e) {
        error(ErrorMapper::ERROR_INFORMATION_MISSING);
        exit;
    }

    /* Teacher in charge
     */
    try {
        /** @var User $teacherInCharge */
        $teacherInCharge = $userMapper->findById($_POST['teacherInCharge']);
    } catch (MapperException\Deleted $e) {
        // Allow a soft-deleted teacher in charge to remain
        /** @var User $teacherInCharge */
        $teacherInCharge = $e->getDeletedObject();
    } catch (MapperException $e) {
        error(ErrorMapper::ERROR_INFORMATION_MISSING);
        exit;
    }

    // EventType
    try {
        /** @var EventType $eventType */
        $eventType = EventTypeMapper::getInstance()->findById($_POST['type']);
    } catch (MapperException $e) {
        error(ErrorMapper::ERROR_INFORMATION_MISSING);
        exit;
    }

    /* Events recur until this time
     */
    if (strlen($_POST['repeat_until'] ?? '')) {
        $until = Alaress_Date_Format::newLocalShort($_POST['repeat_until'])->endOfDay();
    }

    /* Start and end dates
     * (allday input doesn't exist on form, but is supported in the save method below?)
     */
    if ($_POST['allday']) {
        $startDate = Alaress_Date_Format::newLocalShort($_POST['startDate'])->startOfDay();
        $endDate   = Alaress_Date_Format::newLocalShort($_POST['endDate'])->endOfDay();
    } else {
        $startDate = Alaress_Date_Format::newLocal($_POST['startDate']);
        $endDate   = Alaress_Date_Format::newLocal($_POST['endDate']);
    }

    /* Start Date cannot be after End Date
     */
    if ($startDate->getDatabase() >= $endDate->getDatabase()) {
        error(ErrorMapper::ERROR_START_TIME_GREATER);
    }

    /* Spawned further up
     */
    $metadata->setEventType($_POST['type']);
    $metadata->setRamsRequired($_POST['ramsRequired']);
    $metadata->setPermissionSlipRequired(isset($_POST['permissionSlipRequired']));

    /* Only assign createdAt for new metadata records
     */
    if (!$metadata->getId()) {
        $metadata->setCreatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
    }

    $metadata->setUpdatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));

    /* All the staff that were selected
     */
    $staffCollection = new Collection();

    if (is_array($_POST['staff'] ?? false) && count($_POST['staff'])) {
        $staffCollection = $userMapper->findByColumn('id', $_POST['staff']);
    }

    $metadata->setStaffAttendees($staffCollection);

    /* All the resources that were selected
     */
    $resourceBookingCollection = new Collection();

    if (is_numeric($event->getId())) {
        $resourceBookingCollection = EventMetadataMapper::getInstance()->findResourceBookings(
            $metadata->getId()
        );
    }

    $metadata->setResourceBookings($resourceBookingCollection);

    /* Remove files and only re-add what was in the form
     */
    $metadata->setRamsFiles(new Collection());

    if (is_array($_POST['attachment'] ?? false) && count($_POST['attachment']) && $event->getMetadata()->getRamsRequired()) {
        try {
            $factory = new CollectorFactory();
            $files   = $factory->build($_POST['attachment'])->getAll();
        } catch (MapperException $e) {
            error(ErrorMapper::ERROR_FILE_NOT_FOUND);
            exit; // shut UP, storm.
        } catch (\InvalidArgumentException $e) {
            error(ErrorMapper::ERROR_FILE_UPLOAD_INCOMPLETE);
            exit;
        }

        $metadata->setRamsFiles(new Collection($files));
    }

    /* Spawned further up
     */
    $event->setAuthor($author);
    $event->setTeacherInCharge($teacherInCharge);
    $event->setType($eventType);
    $event->setTitle($_POST['note']);
    $event->setStartDateTime($startDate);
    $event->setEndDateTime($endDate);
    $event->setLocation($_POST['location']);
    $event->setDetail($_POST['detail']);
    $event->setAllDay($_POST['allday']);

    /* EOTC events don't use publishDate values, so hardcode this as per
     * SB-5978 to always be published
     */
    $event->setPublishDate('1970-01-01');

    /* Collections of ids
     */
    $event->setCampusCollection(new Collection($_POST['campus']));
    $event->setRoleCollection(new Collection($_POST['role']));

    /* Store metadata
     */
    $event->setMetadata($metadata);

    /* If promoting an event, set to the next status
     * This is a special case for event authors and In Principle status
     */
    $canSelfPromote = $authenticator->canSelfPromote($_SESSION['user'], $event);

    if (isset($_POST['promoteInPrinciple']) && ($_POST['promoteInPrinciple'] == 1) && $canSelfPromote) {
        $eventStatusMapper = EventStatusMapper::getInstance();
        $nextStatusId      = $eventStatusMapper->findNextStatus($event);
        $eventStatus       = $eventStatusMapper->findById($nextStatusId);

        /* Remember original states so we know which obs to spawn
         */
        $originalStatus  = $event->getMetadata()->getStatus()->getId();
        $originalComment = $event->getMetadata()->getComment();

        /* Set status
         */
        /** @var EventStatus $eventStatus */
        $event->getMetadata()->setStatus($eventStatus);
        $event->getMetadata()->setComment($_POST['comment']);

        /* Activity Log
         */
        $registry = new ActivityLogRegistry();
        $factory  = new ActivityLogFactory($event, $_SESSION['user']);

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
    }

    /* Set up individual relations for each event in the series
     * Event object is provided within eventRecurrenceMapper
     */
    $eventRelations = new EventRelations(null, null, null, null, false);

    /* Save this event and any recurring incidents too
     */
    try {
        $eventRecurrenceMapper = new EventRecurrenceMapper($eventMapper);
        $eventRecurrenceMapper->save(
            $event,
            $_POST['efficacy'],
            $_POST['repeat'] ?? false,
            $until,
            $startDate,
            $endDate,
            null,
            $eventRelations
        );
    } catch (InvalidArgumentException $e) {
        error($e->getCode());
    }

    /* Superuser modifying some arbitrary event
     */
    switch ($_POST['redirect']) {
        case 'attendances':
            $page->localRedir(
                '/modules/eotc/manage/attendance/list/' . $eventRecurrenceMapper->getFirstEventIdInSeries()
            );
            break;

        case 'resources':
            $page->localRedir(
                '/modules/eotc/manage/resources/list/' . $eventRecurrenceMapper->getFirstEventIdInSeries()
            );
            break;

        default:
            $page->localRedir(
                '/modules/eotc/manage/modify?eventID=' . $eventRecurrenceMapper->getFirstEventIdInSeries()
            );
            break;
    }
} else {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}
