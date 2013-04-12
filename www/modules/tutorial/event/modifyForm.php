<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';

$page = new SitePage;

$eventHandler = new Event;
$groupHandler = new Group(TUTORIAL_SERVICE);
$userHandler = new User;

// check access
if (($permission = $groupHandler->getPermission(0)) < WRITE_ACCESS)
	$page->error(19);

if (isset($_POST['typeID'])) {

	$hour = $_POST['period'] == 'pm' && $_POST['hour'] < 12 ? $_POST['hour'] + 12 : $_POST['hour'];

	// default finish time to +1 hour
	if (empty($_POST['tohour']))
		$tohour = $hour + 1;
	else
		$tohour = $_POST['toperiod'] == 'pm' && $_POST['tohour'] < 12 ? $_POST['tohour'] + 12 : $_POST['tohour'];

	/* match synergy ids to portal */
	if (is_numeric($_POST['tutor1ID']) && !($tutor1 = $userHandler->getBySynergy($_POST['tutor1ID'])))
		error(31);

	if (is_numeric($_POST['tutor2ID']) && !($tutor2 = $userHandler->getBySynergy($_POST['tutor2ID'])))
		error(31);

	/* set predefined class name/code */
	if ($_POST['typeID'] == 1 && is_numeric($_POST['classID'])) {
		$class = $eventHandler->getClass($_POST['classID']);

		$_POST['name'] = $class['class'];
		$_POST['code'] = $class['code'];
	}

	if (empty($_POST['name']))
		error(2);

	$event = array('typeID'		=> $_POST['typeID'],
				   'classID'	=> $_POST['classID'],
				   'name'		=> $_POST['name'],
				   'code'		=> $_POST['code'],
				   'location'	=> $_POST['location'],
				   'start'		=> $hour.":".(int)$_POST['minute'].":00",
				   'finish'		=> $tohour.":".(int)$_POST['tominute'].":00",
				   'tutor1ID'	=> $tutor1['uid'],
				   'tutor2ID'	=> $tutor2['uid'],
				   'cancelled'	=> isset($_POST['cancelled']) ? 'true' : 'false',
				   );

	if ($_POST['actionType'] == 'sequence' && is_numeric($_POST['eventID'])) {

		/* modify sequence */
		$current = $eventHandler->getByID($_POST['eventID']);

		if ($permission < FULL_ACCESS && !in_array($_SESSION['id'], array($current['tutor1ID'], $current['tutor2ID'])))
			$page->error(19);

		$event['sequenceID'] = $current['sequenceID'];
		$eventHandler->db->update(TUTORIAL_db.'`.`event', 'sequenceID', $event);

		$page->backToTheFuture(3);

	} else {

		if (empty($_POST['date']))
			error(2);

		$dateObj = new Alaress_Date_Format($_POST['date'], 'd/m/y');
		$date = $dateObj->getUnixTimestamp();

		if(is_numeric($_POST['eventID'])) {

			/* modify single event */

			$current = $eventHandler->getByID($_POST['eventID']);

			if ($permission < FULL_ACCESS && !in_array($_SESSION['id'], array($current['tutor1ID'], $current['tutor2ID'])))
				$page->error(19);

			$event['eventID'] = $_POST['eventID'];
			$event['startDate'] = $date->getDatabaseDate();
			$eventHandler->update($event);

			$page->backToTheFuture(2);

		}
		else {

			/* add new event */
			$dateArray = array();

			$untilObj = new Alaress_Date_Format($_POST['repeat_until'], 'd/m/y');
			$until = $untilObj->getUnixTimestamp();

			$validRepeat = array('weekly' => '+1 week', 'fortnightly' => '+2 weeks', 'monthly' => '+1 month');
			if (isset($validRepeat[$_POST['repeat']]) && $until) {
				$i = 0;
				for ($date; $date <= $until; $date = strtotime($validRepeat[$_POST['repeat']], $date)) {
					if ($i++ > 100) break;

					$dateArray[] = $date->getDatabaseDate();
				}

			} else {
				$dateArray[] = $date->getDatabaseDate();
			}

			// check user is one of the tutors
			if ($permission < FULL_ACCESS && !in_array($_SESSION['sid'], array($_POST['tutor1ID'], $_POST['tutor2ID'])))
				$page->error(19);

			// get sequence
			$event['sequenceID'] = $eventHandler->getNewSequenceID();

			for ($i = 0; $i < count($dateArray); $i++) {
				$event['startDate'] = $dateArray[$i];
				$eventID = $eventHandler->create($event);
			}

			$page->backToTheFuture(2);
		}

	}

}
elseif (is_numeric($_GET['delete'])) {

	/* delete event */
	$event = $eventHandler->getByID($_GET['delete']);
	if ($permission < FULL_ACCESS && !in_array($_SESSION['id'], array($event['tutor1ID'], $event['tutor2ID'])))
		$page->error(19);

	$eventHandler->delete($_GET['delete']);
	$page->backToTheFuture(1);
}
elseif (is_numeric($_GET['deleteSequence'])) {

	/* delete sequence */
	$event = $eventHandler->getByID($_GET['deleteSequence']);
	if ($permission < FULL_ACCESS && !in_array($_SESSION['id'], array($event['tutor1ID'], $event['tutor2ID'])))
		$page->error(19);

	$eventHandler->deleteBySequence($event['sequenceID']);
	$page->backToTheFuture(3);
}

?>
