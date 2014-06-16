<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$authenticator = new \EOTC\Authenticator();

if ($authenticator->isEOTCCreator($_SESSION['user'])) {
	$eventMapper = new \EOTC\EventMapper();

	try {
		$event = $eventMapper->findById($this->arg[0]);
	}
	catch (\Exception $e) {
		error(160);
	}

	$groupHandler = new Group();

	$this->setFile(TEMPLATE_content, 'modules/eotc/manage/resources/list.html');
	$this->setBlock(TEMPLATE_content, 'has_resources', 'has_resources_block');

	$this->setVar(array(
		'EVENT_ID' => $event->getId(),
		'EVENT_NAME' => $event->getNote(),
		'EVENT_NAME_ENCODED' => urlencode($event->getNote()),
		'EVENT_START_DATE' => $event->getStartDate(),
		'EVENT_START_TIME' => $event->getStartDateTime()->getUsingFormat('H:i'),
		'EVENT_END_DATE' => $event->getEndDate(),
		'EVENT_END_TIME' => $event->getEndDateTime()->getUsingFormat('H:i'),
	));

	/* Cannot add resources until an event is created
 	 */
	$this->parse('has_resources_block', 'has_resources');
}
else {
	error(19);
}