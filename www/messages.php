<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/** @var SitePage $this */

if(!isset($this)) {
	require_once('../include/Bootstrap.php');
	error(2);
	exit;
}

$this->setFile(TEMPLATE_content, 'messages.html');

$this->setBlock(TEMPLATE_content, 'days', 'days_block');
$this->setBlock('days', 'day', 'day_block');
$this->setBlock('day', 'events', 'events_block');
$this->setBlock('events', 'event', 'event_block');

$this->setTitle('All Messages');


$qf = new \Schoolbox\Notif\Queue\UserQueueFactory();
$q = $qf->bindUserQueue($_SESSION['user']);

$messageArray = $q->getMessages(0, true);

$msgsByDay = array();

foreach($messageArray as $message) {
	$timestamp = $message->getTimestamp();
	$day = date('Y-m-d', $timestamp);
	$msgsByDay[$day][$timestamp][] = $message;

}

krsort($msgsByDay);

$this->setVar('day_block', '');
foreach($msgsByDay as $day => $timestamps) {
	$i = 0;
	krsort($timestamps);

	$custom = array(
		date('Y-m-d')                 => 'today',
		date('Y-m-d', time() - 86400) => 'yesterday'
	);

	if(isset($custom[$day])) {
		$fday = $custom[$day];
	}
	else {
		// TODO format this header date to be prettier
		$fday = $day;
	}

	$this->setVar('DAY', ucfirst($fday));

	$this->setVar('events_block', '');
	foreach($timestamps as $timestamp => $messages) {
		$this->setVar('event_block', '');

		foreach($messages as $message) {
			/** @var \Schoolbox\Notif\Queue\AbstractMessage $message */
			$body = $message->getBody();

			$this->setVar(array(
				'EVENT_BODY' => $body['text'],
				'EVENT_HREF' => $body['href'],
				'EVENT_DARK' => ++$i % 2 ? '' : 'dark',
				'EVENT_TIME' => date('g:ia', $message->getTimestamp()),
				'EVENT_ICON'  => $body['icon']
			));
			$this->parse('event_block', 'event', true);
		}
		$this->parse('events_block', 'events', true);
	}
	$this->parse('day_block', 'day', true);

}

if(count($messageArray)) {
	$this->parse('days_block', 'days');
}
else {
	$this->setVar('days_block', 'You have no messages.');
}


