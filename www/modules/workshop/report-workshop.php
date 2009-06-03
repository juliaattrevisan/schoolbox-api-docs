<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once dirname(__FILE__) . '/library/workshop.php';
require_once SITE_root . '/library/group.php';

$page = new SitePage;
$workshopHandler = new Workshop;
$groupHandler = new Group;

if (!$groupHandler->isStaff() && !$_SESSION['su'])
	error(19);


if (is_numeric($_GET['workshopID'])) {

	$workshop = $workshopHandler->getByID($_GET['workshopID']);
	if (!isset($workshop['workshopID'])) 
		error(2);

	$string = "Synergy ID,Firstname,Surname,Email,Campus,Workshop,Room,Start,Finish\n";

	$userList = $workshopHandler->getUserList($workshop['workshopID']);
	if (is_array($userList)) {
		foreach ($userList as $user) {
			
			$output = array($user['synergy_id'],
							$user['firstname'],
							$user['lastname'],
							formatEmail($user),
							$user['campusName'],
							$workshop['title'],
							$workshop['room'],
							$workshop['start'],
							$workshop['end'],
							);
			
			$string .= outputCSV($output)."\n";
		}
	}

	header('Content-type: text/csv');
	header('Content-Disposition: inline; filename="participant-list-' . convertToURL($workshop['title']) . '.csv"');
	print $string;


}

?>