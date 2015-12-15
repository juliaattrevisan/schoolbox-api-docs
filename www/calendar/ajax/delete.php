<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

// Regrettably I need both the calendar handler and mapper for now,
// as handler contains permission handling methods.
$calendarMapper  = \Schoolbox\Calendar\EventMapper::getInstance();
$calendarHandler = new Calendar();

try {
    $event = $calendarMapper->findById($_POST['eventId']);
} catch (\Schoolbox\Mapper\Exception $e) {
    /* If event can't be found, exit
     */
    exit(1);
}

if (($seriesId = $event->getSeriesId()) != null) {
    // delete all in series
    if ($_POST['efficacy'] == 'series') {
        // rather than wrapping into a single query batch delete, this deletion checks individual permissions on each event
        // (which may not necessarily be the same for each)
        foreach ($calendarMapper->getAllInSeries($seriesId) as $currentEvent) {
            $calendarHandler->delete($currentEvent->getId());
        }
    } else {
        $calendarHandler->delete($event->getId());
    }
} else {
    // delete self
    $calendarHandler->delete($event->getId());
}

exit(1);