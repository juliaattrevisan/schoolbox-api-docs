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

$calendarHandler = new Calendar();
$page = new SitePage();

$event = $calendarHandler->getByID($_POST['eventId']);

/* If event can't be found, exit
 */
if(!is_numeric($event['id'])) {
    exit(1);
}

if(($sID = $calendarHandler->getSeriesID($event['id'])) != null) {

    // delete all in series
    if($_POST['efficacy'] == 'series') {
        $series = $calendarHandler->getAllInSeries($sID);
        // rather than wrapping into a single query batch delete, this deletion checks individual permissions on each event
        // (which may not necessarily be the same for each)
        foreach($series as $currentEvent) {
            $calendarHandler->delete($currentEvent['id']);
        }
    } else {
        $calendarHandler->delete($event['id']);
    }
}
// delete self
else {
    $calendarHandler->delete($event['id']);
}

exit(1);