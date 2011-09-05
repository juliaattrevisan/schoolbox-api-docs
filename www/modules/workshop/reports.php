<?php

require_once SITE_root . '/library/group.php';
require_once dirname(__FILE__) . '/library/workshop.php';

$groupHandler = new Group;
$workshopHandler = new Workshop;

if (!$groupHandler->isStaff() && !$_SESSION['su'])
	error(19);

$this->setFile(TEMPLATE_content, 'modules/workshop/reports.html');
$this->setBlock(TEMPLATE_content, 'workshop', 'workshop_block');

$this->setTitle('Workshop Reports');

$userList = $workshopHandler->getAllUsers();
$this->setVar('TOTAL_USERS', count($userList));

$workshopList = $workshopHandler->getAll('sessionID');
if (is_array($workshopList)) {
	foreach ($workshopList as $workshop) {
		$output = array('WORKSHOP_ID'		=> $workshop['workshopID'],
						'WORKSHOP_TITLE'	=> Scrub::text($workshop['title']),
						'WORKSHOP_BOOKED'	=> $workshop['booked'],
						'ON_OFF'			=> ++$i % 2 ? 'dark' : '',
						);
		$this->setVar($output);
		$this->parse('workshop_block', 'workshop', true);
	}
} else
	$this->setVar('workshop_block', '');

?>