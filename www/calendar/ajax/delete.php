<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Permission\Access\UserAccess;

require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

$calendarMapper = EventMapper::getInstance();
try {
    $event = $calendarMapper->findById($_POST['eventId']);
} catch (\Schoolbox\Mapper\Exception $e) {
    /* If event can't be found, exit
     */
    exit(1);
}

/** @var Event[] $deletableEvents */
$deletableEvents = [];
if ($event->getSeriesId() != null) {
    // delete all in series
    if ($_POST['efficacy'] == 'series') {
        // rather than wrapping into a single query batch delete, this deletion checks individual permissions on each event
        // (which may not necessarily be the same for each)
        $deletableEvents = $calendarMapper->getAllInSeries($event);
    } else {
        $deletableEvents[] = $event;
    }
} else {
    // delete self
    $deletableEvents[] = $event;
}

// Regrettably I need both the calendar handler and mapper for now,
// as handler contains permission handling methods.
$calendarHandler = new Calendar();
foreach ($deletableEvents as $currentEvent) {
    if ($calendarHandler->getPerm($currentEvent->getId()) >= UserAccess::ACCESS_WRITE) {
        $calendarHandler->delete($currentEvent->getId());
    }
}

exit(1);
