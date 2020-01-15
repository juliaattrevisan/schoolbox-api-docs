<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\Event\Access;
use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\Access as FolderAccess;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$groupHandler    = new Group();
$calendarHandler = new Calendar();
$page            = new SitePage();
$userMapper      = new UserMapper();
$eventMapper     = new EventMapper();
$eventAccess     = new Access(FolderAccess::getInstance());

switch ($_POST['action']) {
    case 'Add':
    case 'Update':
        $publishDate = null;
        $until       = null;

        /* Spawn an existing event if id provided
         * Otherwise spawn a new one
         */
        if (is_numeric($_POST['id'])) {

            /* Check event exists
             */
            try {
                $event = $eventMapper->findById($_POST['id']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                error(160);
                exit;
            }

            /* Check user has permission to modify this event
             */
            if ($eventAccess->checkExisting($_SESSION['user'], $event) == false) {
                error(34);
            }

            /* Check owner still exists
             * If not - pass ownership to the editor
             */
            try {
                $owner = $userMapper->findById($event->getOwner()->getId());
            } catch (\Schoolbox\Mapper\Exception $e) {
                $owner = $_SESSION['user'];
            }

        } else {
            $event = new Event();
            $owner = $userMapper->findById($_POST['userId']);
        }

        // Check if author still exists
        // If not - pass author to the editor
        try {
            $author = $userMapper->findById($_POST['authorId']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $author = $_SESSION['user'];
        }

        /* Check user has permission to write the event with posted settings
         */
        $groups = isset($_POST['group']) ? $_POST['group'] : [];
        $campuses = isset($_POST['campus']) ? $_POST['campus'] : [];

        if ($eventAccess->checkDestination(
                $_SESSION['user'],
                $_POST['level'],
                $author,
                $owner,
                $groups,
                $campuses
            ) == false) {
            error(34);
        }

        /* Events aren't visible until this date
         * TODO: Fix validator in Alaress Date Format
         */
        // Ghetto validation
        try {
            if (strlen($_POST['publishDate'])) {
                $pubDateObject = Alaress_Date_Format::newLocal($_POST['publishDate']);
            }
            /* Events recur until this time
             */
            if (strlen($_POST['repeat_until'])) {
                $until = Alaress_Date_Format::newLocalShort($_POST['repeat_until'])->endOfDay();
            }
            /* Start and end dates
             */
            if($_POST['allday'] == 1) {
                $startDate = Alaress_Date_Format::newLocalShort($_POST['startDate'])->startOfDay();
                $endDate = Alaress_Date_Format::newLocalShort($_POST['endDate'])->endOfDay();
            }
            else {
                $startDate = Alaress_Date_Format::newLocal($_POST['startDate']);
                $endDate = Alaress_Date_Format::newLocal($_POST['endDate']);
            }
        } catch (RuntimeException $e) {
            error(15);
            exit;
        }
        // Don't allow start to be after end
        if ($startDate->getUnixTimeStamp() > $endDate->getUnixTimeStamp()) {
            error(145);
            exit;
        }
        /* We do this checking later
         */
//        if ($_POST['level'] == 2) {
//            if (!(is_array($_POST['group']) && count($_POST['group']))) {
//                error(2);
//            }
//        }

        /* NO RETREAT
         * NO SURRENDER
         */

        // spawned further up
        $event->setAuthor($author);
        $event->setOwner($owner);
        $event->setAccess($_POST['level']);
        $event->setType(EventTypeMapper::getInstance()->createModel()->setId($_POST['type']));
        $event->setTitle($_POST['title']);
        $event->setStartDateTime($startDate);
        $event->setEndDateTime($endDate);
        $event->setLocation($_POST['location']);
        $event->setDetail($_POST['detail']);
        $event->setPublishDateTime($pubDateObject);
        $event->setAllDay($_POST['allday']);

        /* Collections of ids
         */
        $event->setRoleCollection(
            new \Schoolbox\Collection(isset($_POST['groupCheck']) ? $_POST['groupCheck'] : array())
        );
        $event->setCampusCollection(new \Schoolbox\Collection($_POST['campus']));

        /* Attendance dirty bit should be set to true if the attendance
         * setting now differs from what it previously was
         */
        if ($event->getAttendance() != $_POST['attendance']) {
            $event->setAttendance($_POST['attendance']);
            $event->setAttendanceDirty(true);
        }

        if ($event->isCampusLevel()) {
            /* Check at least one campus has been selected
             */
            if (!is_array($_POST['campus']) || !count($_POST['campus'])) {
                error(158);
                exit;
            }
        } else if ($event->isGroupLevel()) {
            /* Check at least one group has been selected
             */
            if (!is_array($_POST['group']) || !count($_POST['group'])) {
                error(2);
                exit;
            }
        }

        /* Set up individual relations for each event in the series
         * Event object is provided within eventRecurrenceMapper
         */
        $eventRelations = new \Schoolbox\Calendar\EventRelations($_POST['level'], $_POST['group'], $_POST['roles']);

        /* Save this event and any recurring incidents too
         */
        try {
            $eventRecurrenceMapper = new \Schoolbox\Calendar\EventRecurrenceMapper($eventMapper);
            $eventRecurrenceMapper->save($event, $_POST['efficacy'], $_POST['repeat'], $until, $startDate, $endDate, $_POST['level'], $eventRelations);
        } catch (InvalidArgumentException $e) {
            error($e->getCode());
        }

        /* If you were editing an event on someone else's calendar, don't
         * get stuffed around by time travel's one-index-offset rip in
         * the space-time continuum
         */
        /* Superuser modifying some arbitrary event
         */
        if (!in_array($_SESSION['user']->getId(), array($event->getAuthor()->getId(), $event->getOwner()->getId()))) {
                $page->backToTheFuture(3);
            } elseif (
                ($event->getAuthor()->getId() != $event->getOwner()->getId())
                && ($event->getAuthor()->getId() == $_SESSION['user']->getId())
            ) {
                /* Teacher modifying self-created event for some arbitrary person
                 */
                $page->backToTheFuture(3);
            } elseif (!empty($_POST['return'])) {
                $page->localRedir($_POST['return']);
            } else {
                $page->backToTheFuture(2);
            }
        break;

    case 'Delete':

        $eventMapper = new \Schoolbox\Calendar\EventMapper();

        /**
         * Check event exists
         */
        try {
            $event = $eventMapper->findById($_POST['id']);
        } catch (\Exception $e) {
            error(160);
        }

        /**
         * Check user has permission to delete this event
         */
        if ($eventAccess->checkExisting($_SESSION['user'], $event) == false) {
            error(34);
        }

        /* Delete an entire series, if the seriesId is not null
         */
        if ($event->getSeriesId() != null) {
            if ($_REQUEST['efficacy'] == 'series') {
                $allEvents = $eventMapper->getAllInSeries($event);
                foreach ($allEvents as $currentEvent) {
                    $eventMapper->delete($currentEvent);
                }
            } elseif ($_REQUEST['efficacy'] == 'self') {
                $eventMapper->delete($event);
            }
        } else {
            $eventMapper->delete($event);
        }

        $redirect = $event->setupRedirect(
            urldecode($_GET['returnTo']),
            ($_GET['returnDate'] ? $_GET['returnDate'] : $event->getStartDateTime()->getURLDate()),
            $_GET['homepageId'],
            $_GET['userId']);
        header('Content-type: application/json');
        echo json_encode(['callbackUrl' => $redirect]);
        break;

    default:
        error(0);
        break;
}
