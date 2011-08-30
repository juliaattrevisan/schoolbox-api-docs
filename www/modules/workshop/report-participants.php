<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once dirname(__FILE__) . '/library/workshop.php';
require_once SITE_root . '/library/group.php';

$page = new SitePage;
$workshopHandler = new Workshop;
$groupHandler = new Group;

if (!$groupHandler->isStaff() && !$_SESSION['su'])
	error(19);

$string = "Synergy ID,Firstname,Surname,Email,Campus,Session 1,Session 1 Room,Session 2,Session 2 Room,Session 3,Session 3 Room,Session 4,Session 4 Room\n";

$userList = $workshopHandler->getAllUsers();
if (is_array($userList)) {
	foreach ($userList as $user) {

		$workshopList = $workshopHandler->getByUser($user['id']);
		$session = array();
		if (is_array($workshopList)) {
			foreach ($workshopList as $workshop) {
				$session[$workshop['sessionID']] = $workshop;
			}
		}

		$emailObj = new Email();
		$email = $emailObj->formatEmailFromArray($user);

		$output = array($user['synergy_id'],
						$user['firstname'],
						$user['lastname'],
						$email,
						$user['campusName'],
						$session[1]['title'],
						$session[1]['room'],
						$session[2]['title'],
						$session[2]['room'],
						$session[3]['title'],
						$session[3]['room'],
						$session[4]['title'],
						$session[4]['room'],
						);

		$string .= TextFormatter::outputCSV($output)."\n";
	}
}

header('Content-type: text/csv');
header("Pragma: ");
header("Cache-control: ");
header('Content-Disposition: inline; filename="participant-list-all.csv"');
print $string;


?>