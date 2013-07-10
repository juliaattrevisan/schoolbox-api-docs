<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');
$calendarHandler = new Calendar();
$page = new SitePage();

switch($_POST['action']) {
    case 'Add':
    case 'Update':
        $untilObj = new Alaress_Date_Format($_POST['repeat_until'], 'd/m/y');
        $until = $untilObj->getUnixTimestamp();

        /* Events aren't visible until this date
          */
        if($_POST['publishDate']) {
            $pubDateObject = new Alaress_Date_Format($_POST['publishDate'], 'd/m/y');
	        $publishDate = $pubDateObject->getDatabaseDate();
        }
        else {
            $pubDateObject = 'NULL';
        }


		$start = $_POST['startDate'] . ' ' . $_POST['startHour'] . ':' . $_POST['startMinute'] . ' ' . $_POST['startPeriod'];
		$end = $_POST['endDate'] . ' ' . $_POST['endHour'] . ':' . $_POST['endMinute'] . ' ' . $_POST['endPeriod'];

		$startDate = new Alaress_Date_Format($start, 'd/m/y h:i z');
		$endDate = new Alaress_Date_Format($end, 'd/m/y h:i z');

        $event = array(
			'owner' => $_SESSION['id'],
			'access' => $_POST['level'],
			'sid' => $_POST['group'],
			'campusList' => !empty($_POST['campus']) ? $_POST['campus'] : array($_SESSION['campus'][0]),
			'groupList' => $_POST['groupCheck'],
			'type' => $_POST['type'],
			'note' => $_POST['note'],
			'allday' => !empty($_POST['allday']),
			'startDate' => $startDate->getDatabaseDate(),
			'startTime' => $startDate->getDatabaseTime(),
			'endDate' => $endDate->getDatabaseDate(),
			'endTime' => $endDate->getDatabaseTime(),
			'location' => $_POST['location'],
			'attendance' => $_POST['attendance'],
			'detail' => $_POST['detail'],
			'publish_date' => $publishDate,
        );

        $validRepeat = array(
			'daily' => '+1 day',
			'weekly' => '+1 week',
			'fortnightly' => '+2 weeks',
			'monthly' => '+1 month'
        );

        // repeat specified, and and enddate also, populate the dates to be set
        if (isset($validRepeat[$_POST['repeat']]) && $until) {
            $i = 0;

            $event['series_id'] = $calendarHandler->getUpperSeriesIndex() + 1;

            //
            // Repeat of events: repeat until the start date is after the until
            // date (or x100, whatever is less)
            for ($start_timestamp = $startDate->getUnixTimestamp(),
                 $end_timestamp   = $endDate->getUnixTimestamp();
                 $start_timestamp <= $until;
                 $start_timestamp = strtotime($validRepeat[$_POST['repeat']], $start_timestamp),
                 $end_timestamp   = strtotime($validRepeat[$_POST['repeat']], $end_timestamp)
            ) {
                if ($i++ > 100) {
                    break;
                }
                $this_start_date = new Alaress_Date_Format($start_timestamp, 'U');
                $this_end_date   = new Alaress_Date_Format($end_timestamp,   'U');
                $dateArray[] = array(
                    'start' => $this_start_date->getDatabaseDate(),
                    'end'   => $this_end_date->getDatabaseDate(),
                );
            }
        }

        // if repeat is set, but no enddate was specified, bail out
        else if(isset($validRepeat[$_POST['repeat']]) && (!$until)) {
            error(2); // "not enough data submitted to the form"
        }

        // no repeat
        else {
            $dateArray[] = array(
                'start' => $startDate->getDatabaseDate(),
                'end'   => $endDate->getDatabaseDate(),
            );
        }

        if (is_numeric($_POST['id'])) {
            if ($_POST['efficacy'] == 'series') {
                // don't override the date or seriesID
                unset($event['startDate']);
                unset($event['endDate']);
                unset($event['series_id']);

                $series = $calendarHandler->getAllInSeriesByEventID($_POST['id']);

                foreach($series as $seriesEvent) {
                    $event['id'] = $seriesEvent['id'];
                    $calendarHandler->update($event);
                }
            }
            else if($_POST['efficacy'] == 'self') {
                //
                // If there's any recurrence: update first only, and create the
                // rest from scratch.
                $first_date_set     = array_shift($dateArray);
                $event['id']        = $_POST['id'];
                $event['startDate'] = $first_date_set['start'];
                $event['endDate']   = $first_date_set['end'];
                $calendarHandler->update($event);
                foreach ($dateArray as $dates) {
                    unset($event['id']);
                    $event['startDate'] = $dates['start'];
                    $event['endDate']   = $dates['end'];
                    $calendarHandler->create($event);
                }
            }
        }
        else {
            foreach ($dateArray as $dates) {
                $event['startDate'] = $dates['start'];
                $event['endDate']   = $dates['end'];
                $calendarHandler->create($event);
            }
        }

		// instead of just going back to the future, send the user to the calendar view that shows them their event
        $page->localRedir('/calendar/full/' . $startDate->getUsingFormat('d-m-Y'));
        break;

    case 'Delete':
        if(!is_numeric($_POST['id'])) {
            error(0);
        }

        if (($sID = $calendarHandler->getSeriesID($_POST['id'])) != null) {
            // delete all in series
            if ($_POST['efficacy'] == 'series') {
                $series = $calendarHandler->getAllInSeries($sID);
                // rather than wrapping into a single query batch delete, this deletion checks individual permissions on each event
                // (which may not necessarily be the same for each)
                foreach($series as $event) {
                    $calendarHandler->delete($event['id']);
                }
            }
			else if($_POST['efficacy'] == 'self') {
                $calendarHandler->delete($_POST['id']);
            }
        }
        // delete self
        else {
            $calendarHandler->delete($_POST['id']);
        }

        $page->backToTheFuture(2);
        break;

    default:
        error(0);
        break;
}


if (isset($_POST['date'])) {

}
else if (isset($_GET['delete'])) {
    $calendar = $calendarHandler->getByID($_GET['delete']);
    $calendarHandler->delete($_GET['delete']);

    $date = explode("-", $calendar['date']);
    $date = array_reverse($date);

    $page->backToTheFuture(2);

}
