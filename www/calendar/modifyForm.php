<?php
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Calendar\EventRelations;

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$groupHandler    = new Group();
$calendarHandler = new Calendar();
$page            = new SitePage();

$userMapper = new \Schoolbox\UserMapper();

/**
 * Gets owner out of this event, or errors out if they don't have permission.
 *
 * @param int $userId
 *
 * @return bool
 */
$validateOwner = function ($userId) use ($groupHandler, $userMapper) {
    try {
        if ($groupHandler->isSuperuser()
            || $groupHandler->isStaff()
            || $groupHandler->isParentOfChild($userId)
            || ($_SESSION['user']->getId() == $userId)
        ) {
            return $userMapper->findById($userId);
        } else {
            /* You're not supposed to be able to create events under other circumstances
             */
            error(19);
            exit;
        }
    } catch (\Schoolbox\Mapper\Exception $e) {
        return $userMapper->createModel();
    }
};

/* Superuser/Staff/Parent adding event for someone else or
 * someone creating their own event
 */

switch ($_REQUEST['action']) {
    case 'Add':
    case 'Update':
        /* Default
         */
        $publishDate = null;
        $until       = null;
        $eventMapper = new \Schoolbox\Calendar\EventMapper();
        /* Spawn an existing event if id provided
         * Otherwise spawn a new one
         */
        if (is_numeric($_POST['id'])) {
            try {
                $event = $eventMapper->findById($_POST['id']);
                // Validate event's existing owner too
                $validateOwner($event->getOwner()->getId());
            } catch (\Schoolbox\Mapper\Exception $e) {
                error(160);
                exit;
            }
        } else {
            $event = new Event();
        }
        $owner = $validateOwner($_POST['userId']);
        /* Events aren't visible until this date
         * TODO: Fix validator in Alaress Date Format
         */
        // Ghetto validation
        try {
            if (strlen($_POST['publishDate'])) {
                $pubDateObject = Alaress_Date_Format::newLocalShort($_POST['publishDate']);
            }
            /* Events recur until this time
             */
            if (strlen($_POST['repeat_until'])) {
                $until = Alaress_Date_Format::newLocalShort($_POST['repeat_until'])->endOfDay();
            }
            /* Start and end dates
             */
            $startDate = Alaress_Date_Format::newLocalShort($_POST['startDate'])
                ->set12Hour($_POST['startHour'], $_POST['startPeriod'])
                ->setMinute($_POST['startMinute'])
            ;
            $endDate = Alaress_Date_Format::newLocalShort($_POST['endDate'])
                ->set12Hour($_POST['endHour'], $_POST['endPeriod'])
                ->setMinute($_POST['endMinute'])
            ;
        } catch (RuntimeException $e) {
            error(15);
            exit;
        }
        // Don't allow start to be after end
        if ($startDate->getUnixTimestamp() > $endDate->getUnixTimestamp()) {
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
        try {
            // Author might've been deleted, so don't die if they don't exist
            $event->setAuthor($userMapper->findById($_POST['authorId']));
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop
        }
        /* Spawned further up
         */
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
        // Force events to have at least one campus value selected (if a group event)
        if ($event->isCampusLevel()) {
            if (!is_array($_POST['campus']) || !count($_POST['campus'])) {
                error(158);
                exit;
            }
        }

        // Error out if user don't have write access to requested groups
        if ($event->isGroupLevel()) {
            if (!is_array($_POST['group']) || !count($_POST['group'])) {
                error(2);
                exit;
            }
            foreach ($_POST['group'] as $group) {
                if ($groupHandler->getPermission($group, $owner->getId()) < WRITE_ACCESS) {
                    error(34);
                    exit;
                }
            }
        }

        /* Set up individual relations for each event in the series
         * Event object is provided within eventRecurrenceMapper
         */
        $eventRelations = new \Schoolbox\Calendar\EventRelations($_POST['level'], $_POST['group'], $_POST['roles']);

        /* Save this event and any recurring incidents too
         */
        $eventRecurrenceMapper = new \Schoolbox\Calendar\EventRecurrenceMapper($eventMapper);
        $eventRecurrenceMapper->save($event, $_POST['efficacy'], $_POST['repeat'], $until, $startDate, $endDate, $_POST['level'], $eventRelations);

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
        try {
            $event = $eventMapper->findById($_REQUEST['id']);
            // Validate owner of this event can actually delete this event
            $validateOwner($event->getOwner()->getId());
        } catch (\Exception $e) {
            error(160);
            exit;
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
        // GET: from full calendar
        // POST: from calendar event modify page
        // TODO figure out a global method for redirecting!
        if (isset($_GET['id'])) {
            $page->backToTheFuture(1);
        } else {
            $page->backToTheFuture(2);
        }
        break;

    default:
        error(0);
        break;
}
