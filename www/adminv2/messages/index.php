<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if(!isset($this)) {
	require_once __DIR__ . '/../../../include/Bootstrap.php';
	SitePage::localRedir('/adminv2/messages');
}

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
	error(19);
}

/** @var SitePage $this */

$this->setFile(TEMPLATE_content, '/adminv2/messages/index.html');
$nav = new AdminV2_Nav();
$nav->parse($this, 'messages');
$this->setTitle('Admin - Messages');

$this->setBlock(TEMPLATE_content, 'role_digest', 'role_digest_block');
$this->setBlock('role_digest', 'day_check', 'day_check_block');
$this->setBlock('role_digest', 'time_select', 'time_select_block');

// compile a week to test against
/** @var DateTime[] $dayTests */
$dayTests = array();
for($i = 0; $i < 7; ++$i) {
	$d = new DateTime("+$i day");
	$dayTests[$d->format('w')] = $d;
}
// aaaand put 0 at the start
ksort($dayTests);

$timeDatetime = new DateTime();
$timeTests = array();
for($i = 0; $i < 24; ++$i) {
	for($j = 0; $j < 60; $j += 30) {
		$timeTests[] = array($i, $j);
	}
}


foreach(\Schoolbox\RoleMapper::getInstance()->findAll() as $role) {

	/** @var \Schoolbox\Role $role */

	$this->setVar(array(
		'ROLE_NAME' => $role->getName(),
		'ROLE_ID'   => $role->getId()
	));

	$schedule = $role->getDigestSchedule();

	switch($schedule) {
		case \Schoolbox\Role::DIGEST_DAILY:
			$schedule = \Schoolbox\Realtime\Digest\DigestScheduler::DAILY;
			break;
		case \Schoolbox\Role::DIGEST_WEEKLY:
			$schedule = \Schoolbox\Realtime\Digest\DigestScheduler::WEEKLY;
			break;
	}

	try {
		$datecron = \Cron\CronExpression::factory($schedule)
			->setPart(\Cron\CronExpression::MINUTE, '*')
			->setPart(Cron\CronExpression::HOUR, '*');

		$timecron = \Cron\CronExpression::factory($schedule)
			->setPart(\Cron\CronExpression::DAY, '*')
			->setPart(\Cron\CronExpression::MONTH, '*')
			->setPart(\Cron\CronExpression::WEEKDAY, '*')
			->setPart(\Cron\CronExpression::YEAR, '*');
	}
	catch(InvalidArgumentException $e) {
		// no schedule for group
		$datecron = \Cron\CronExpression::factory(\Schoolbox\Realtime\Digest\DigestScheduler::NEVER);
		$timecron = \Cron\CronExpression::factory(\Schoolbox\Realtime\Digest\DigestScheduler::NEVER);
	}

	foreach($dayTests as $i => $date) {
		try {
			$checked = $datecron->isDue($date);
		}
		catch(RuntimeException $e) {
			$checked = false;
//			print_r($datecron->getExpression());
//			echo $e;
		}
		$char = substr($date->format('D'), 0, 1);

		$this->setVar(array(
			'DAY_VALUE' => $i,
			'DAY_CHECKED' => $checked ? 'checked="checked"' : '',
			'DAY_CHAR' => $char
		));

		$this->parse('day_check_block', 'day_check', true);
	}

	try {
		$nextTime = $timecron->getNextRunDate();
		$hr = $nextTime->format('H');
		$mi = $nextTime->format('i');
		$valid = true;
	}
	catch(RuntimeException $e) {
		$valid = false;
	}


	foreach($timeTests as $time) {
		if($valid) {
			$selected = ($hr == $time[0] && $mi == $time[1]);
		}
		else {
			$selected = false;
		}

		$this->setVar(array(
			'TIME_VALUE' => sprintf("%02d:%02d", $time[0], $time[1]),
			'TIME_SELECTED' => $selected ? 'selected="selected"' : ''
		));

		$this->parse('time_select_block', 'time_select', true);
	}

	$this->parse('role_digest_block', 'role_digest', true);
	$this->setVar('day_check_block', '');
	$this->setVar('time_select_block', '');

}