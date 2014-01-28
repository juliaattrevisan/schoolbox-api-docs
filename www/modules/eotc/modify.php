<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* Modify existing event
 */
if (is_numeric($this->arg[0])) {
	$mapper = new \EOTC\CalendarEventMapper();
	$event = $mapper->findById($this->arg[0]);

	new \Alaress_Probe($event);
}
/* Create new event
 */
else {
	$this->setFile(TEMPLATE_content, 'modules/eotc/modify.html');

	$this->setVar(array(
		'EVENT_ID' => '',
		'EVENT_USER_ID' => $_SESSION['user']->getId(),
		'EVENT_AUTHOR_ID' => $_SESSION['user']->getId(),
		'EVENT_NOTE' => '',
		'EVENT_START_DATE' => '',
		'EVENT_START_HOUR' => '',
		'EVENT_START_MIN' => '',
		'EVENT_START_AM' => '',
		'EVENT_START_PM' => '',
		'EVENT_END_DATE' => '',
		'EVENT_END_HOUR' => '',
		'EVENT_END_MIN' => '',
		'EVENT_END_AM' => '',
		'EVENT_END_PM' => '',
		'EVENT_ALLDAY_CHECKED' => '',
		'EVENT_PUBLISH_DATE' => '',
	));

	/* Hide advanced options by default
	 */
	$this->setVar(array(
		'SHOW_ADVANCED' => '',
		'ADVANCED_SHOW' => '',
		'ADVANCED_CLOSE' => 'hidden',
	));
}