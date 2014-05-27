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
$this->setBlock('event', 'abstract', 'abstract_block');

$this->setTitle('All Messages');


$qf = new \Schoolbox\Notif\Queue\UserQueueFactory(new \Schoolbox\Notif\MQ\Test\MockAMQPConnector(array(
	new \PhpAmqpLib\Message\AMQPMessage('{"message":"{\"title\":{\"text\":\":user posted :title under :topic\",\"params\":{\"user\":{\"text\":\"Alaress Admin\",\"href\":\"\\\/search\\\/user\\\/19\",\"mode\":1},\"title\":{\"text\":\"6WeBsOcKeT\",\"href\":\"\\\/news\\\/item\\\/23\",\"mode\":1},\"topic\":{\"text\":\"Sport\",\"href\":\"\\\/news\\\/academic\",\"mode\":1}}},\"namespace\":\"news\",\"time\":1401109170,\"icon\":\"\\\/portrait.php?userId=485&size=square32\",\"href\":\"\\\/news\\\/item\\\/23\",\"meta\":{\"byline\":\"26th May 2014 by Alaress Admin in Academic\",\"body\":\"You have no idea who you are dealing with.\"}}","context":[],"level":250,"level_name":"NOTICE","channel":"instant.user.10","datetime":{"date":"2014-05-26 21:19:39","timezone_type":3,"timezone":"Australia\/Victoria"},"extra":[]}'),
	new \PhpAmqpLib\Message\AMQPMessage('{"message":"{\"title\":{\"text\":\":user posted :title under :topic\",\"params\":{\"user\":{\"text\":\"Alaress Admin\",\"href\":\"\\\/search\\\/user\\\/19\",\"mode\":1},\"title\":{\"text\":\"1Websocket\",\"href\":\"\\\/news\\\/item\\\/23\",\"mode\":1},\"topic\":{\"text\":\"Sport\",\"href\":\"\\\/news\\\/academic\",\"mode\":1}}},\"namespace\":\"news\",\"time\":1401101170,\"icon\":\"\\\/portrait.php?userId=485&size=square32\",\"href\":\"\\\/news\\\/item\\\/23\",\"meta\":{\"byline\":\"26th May 2014 by Alaress Admin in Academic\",\"body\":\"You have no idea who you are dealing with.\"}}","context":[],"level":250,"level_name":"NOTICE","channel":"instant.user.10","datetime":{"date":"2014-05-26 21:19:39","timezone_type":3,"timezone":"Australia\/Victoria"},"extra":[]}'),
	new \PhpAmqpLib\Message\AMQPMessage('{"message":"{\"title\":{\"text\":\":user posted :title under :topic\",\"params\":{\"user\":{\"text\":\"Alaress Admin\",\"href\":\"\\\/search\\\/user\\\/19\",\"mode\":1},\"title\":{\"text\":\"4Sebwocket\",\"href\":\"\\\/news\\\/item\\\/23\",\"mode\":1},\"topic\":{\"text\":\"Academic\",\"href\":\"\\\/news\\\/academic\",\"mode\":1}}},\"namespace\":\"news\",\"time\":1401103170,\"icon\":\"\\\/portrait.php?userId=485&size=square32\",\"href\":\"\\\/news\\\/item\\\/23\",\"meta\":{\"byline\":\"26th May 2014 by Alaress Admin in Academic\",\"body\":\"Do not make me come down there.\"}}","context":[],"level":250,"level_name":"NOTICE","channel":"instant.user.10","datetime":{"date":"2014-05-26 21:19:39","timezone_type":3,"timezone":"Australia\/Victoria"},"extra":[]}'),
	new \PhpAmqpLib\Message\AMQPMessage('{"message":"{\"title\":{\"text\":\":user posted :title under :topic\",\"params\":{\"user\":{\"text\":\"Alaress Admin\",\"href\":\"\\\/search\\\/user\\\/19\",\"mode\":1},\"title\":{\"text\":\"2Bewsocket\",\"href\":\"\\\/news\\\/item\\\/23\",\"mode\":1},\"topic\":{\"text\":\"Academic\",\"href\":\"\\\/news\\\/academic\",\"mode\":1}}},\"namespace\":\"news\",\"time\":1401102170,\"icon\":\"\\\/portrait.php?userId=485&size=square32\",\"href\":\"\\\/news\\\/item\\\/24\",\"meta\":{\"byline\":\"26th May 2014 by Alaress Admin in Academic\",\"body\":\"My name is Inigo Montoya.\"}}","context":[],"level":250,"level_name":"NOTICE","channel":"instant.user.10","datetime":{"date":"2014-05-26 21:19:39","timezone_type":3,"timezone":"Australia\/Victoria"},"extra":[]}'),
	new \PhpAmqpLib\Message\AMQPMessage('{"message":"{\"title\":{\"text\":\":user posted :title under :topic\",\"params\":{\"user\":{\"text\":\"Alaress Admin\",\"href\":\"\\\/search\\\/user\\\/19\",\"mode\":1},\"title\":{\"text\":\"5tekcosbeW\",\"href\":\"\\\/news\\\/item\\\/23\",\"mode\":1},\"topic\":{\"text\":\"Sport\",\"href\":\"\\\/news\\\/academic\",\"mode\":1}}},\"namespace\":\"news\",\"time\":1401105170,\"icon\":\"\\\/portrait.php?userId=485&size=square32\",\"href\":\"\\\/news\\\/item\\\/23\",\"meta\":{\"byline\":\"26th May 2014 by Alaress Admin in Academic\",\"body\":\"Who could have known that this would be the outcome?\"}}","context":[],"level":250,"level_name":"NOTICE","channel":"instant.user.10","datetime":{"date":"2014-05-26 21:19:39","timezone_type":3,"timezone":"Australia\/Victoria"},"extra":[]}'),
	new \PhpAmqpLib\Message\AMQPMessage('{"message":"{\"title\":{\"text\":\":user posted :title under :topic\",\"params\":{\"user\":{\"text\":\"Alaress Admin\",\"href\":\"\\\/search\\\/user\\\/19\",\"mode\":1},\"title\":{\"text\":\"3Web$ocket\",\"href\":\"\\\/news\\\/item\\\/23\",\"mode\":1},\"topic\":{\"text\":\"Academic\",\"href\":\"\\\/news\\\/academic\",\"mode\":1}}},\"namespace\":\"news\",\"time\":1401104170,\"icon\":\"\\\/portrait.php?userId=485&size=square32\",\"href\":\"\\\/news\\\/item\\\/24\",\"meta\":{\"byline\":\"26th May 2014 by Alaress Admin in Academic\",\"body\":\"Look, just because I may have done that thing, does that mean I should necessarily die?\"}}","context":[],"level":250,"level_name":"NOTICE","channel":"instant.user.10","datetime":{"date":"2014-05-26 21:19:39","timezone_type":3,"timezone":"Australia\/Victoria"},"extra":[]}'),
)));
$q = $qf->bindUserQueue($_SESSION['user']);

// load the 20 most recent notifications into a decorator
$decorator = new \Schoolbox\Realtime\Notification\Decorator();
$decorator->appendNotifications($q->getNotifications(20, true, true));

$notifications = $decorator->renderAsSitePageArray();

$msgsByDay = array();

foreach($notifications as $id => $notification) {
	$timestamp = $notification['time'];
	$day = date('Y-m-d', $timestamp);
	$msgsByDay[$day][$timestamp][$id] = $notification;

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
	foreach($timestamps as $timestamp => $notifs) {
		$this->setVar('event_block', '');
		foreach($notifs as $n) {
			$this->setVar(array(
				'EVENT_BODY'     => $n['title'],
				'EVENT_HREF'     => $n['href'],
				'EVENT_ICON'     => $n['icon'],
				'EVENT_TIME'     => $n['timeonly'],
				'EVENT_LONGTIME' => $n['longtime'],
				'EVENT_DARK'     => ++$i % 2 ? '' : 'dark',
			));

			if(isset($n['meta']['body']) && strlen($n['meta']['body'])) {
				$this->setVar('EVENT_ABSTRACT', TextFormatter::cutSentence($n['meta']['body'], 48));
				$this->parse('abstract_block', 'abstract');
			}
			else {
				$this->setVar('abstract_block', '');
			}


			$this->parse('event_block', 'event', true);
		}
		$this->parse('events_block', 'events', true);
	}
	$this->parse('day_block', 'day', true);

}

if(count($notifications)) {
	$this->parse('days_block', 'days');
}
else {
	$this->setVar('days_block', 'You have no messages.');
}


