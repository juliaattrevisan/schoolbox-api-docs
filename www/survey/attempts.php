<?php
/**
 * Attempts
 *
 * Show a list of attempts for a particular Survey.
 * You can delete attempts from this screen.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
$groupHandler = new Group();
$assessHandler = new Survey_Main();
$attemptHandler = new Survey_Attempt();

$assess = $assessHandler->getByID($this->arg[0]);

if (!$assess) {
	error(112);
}

if (!$groupHandler->isStaff()) {
    error(126);
}

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

if (!$assessHandler->isOwner($this->arg[0]) && !$groupHandler->isSuperuser()) {
    error(125);
}

$this->setFile(TEMPLATE_content, 'survey/attempts.html');
$this->setBlock(TEMPLATE_content, 'attempt', 'attempt_block');


$allAttempts = $attemptHandler->getList($this->arg[0]);

if (is_array($allAttempts) && count($allAttempts)) {
	$i = 0;

	foreach($allAttempts as $currentAttempt) {
		$i++;

		$startDate = new Alaress_Date_Format($currentAttempt['start'], 'y-m-d h:i:s');
		$finishDate = new Alaress_Date_Format($currentAttempt['finish'], 'y-m-d h:i:s');

		$this->setVar(array(
			'ATTEMPT_USER_ID' => $currentAttempt['user_id'],
			'ATTEMPT_FIRSTNAME' => $currentAttempt['firstname'],
			'ATTEMPT_LASTNAME' => $currentAttempt['lastname'],
			'ATTEMPT_START' => $startDate->getLocalShort(),
			'ATTEMPT_FINISH' => $finishDate->getLocalShort(),
			'ATTEMPT_REVIEWED_AT' => $currentAttempt['reviewed_at'],
			'ATTEMPT_ATTEMPT_ID' => $currentAttempt['attempt_id'],
			'ON_OFF' => $i % 2 ? 'dark' : ''
		));

		$this->parse('attempt_block', 'attempt', true);
	}
}
else {
	$this->setVar('attempt_block', '');
}

$this->setVar(array(
	'SURVEY_NAME' => $assess['title']
));
