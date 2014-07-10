<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$groupHandler = new Group();

if ($groupHandler->getPermission($_POST['homepageId']) < WRITE_ACCESS) {
	error(19);
}
else {
	$user = $_SESSION['user'];
	$author = $_SESSION['user'];
	$categoryList = array($_POST['homepageId']);
	$groupList = array();
	$campusList = array();
	$file = $_FILES['file']['tmp_name'];

	if (strlen($_FILES['file']['tmp_name']) && $_FILES['file']['size']) {
		$factory = new \Calendar\Import\Factory($user, $author, $categoryList, $groupList, $campusList, \Schoolbox\Calendar\Event::ACCESS_GROUP, $file);
		$strategy = $factory->build();
		$strategy->load();

		$allEvents = $strategy->getOutput();

		$this->setFile(TEMPLATE_content, 'calendar/importForm.html');
		$this->setBlock(TEMPLATE_content, 'event', 'event_block');

		if ($allEvents && count($allEvents)) {
			foreach ($allEvents as $currentEvent) {
				$this->setVar(array(
					'EVENT_NAME' => $currentEvent['name'],
					'EVENT_STATUS' => $currentEvent['status'],
					'EVENT_ERROR' => $currentEvent['error'],
				));

				$this->parse('event_block', 'event', true);
			}
		}
		else {
			$this->setVar('event_block', '');
		}
	}
	else {
		error(2);
	}
}
