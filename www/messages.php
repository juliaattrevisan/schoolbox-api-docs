<?php
use Alaress\Redis;
use Schoolbox\Realtime\MessageList\ListFactory;
use Schoolbox\Realtime\Notification\Decorator;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// not in SitePage context?
if(!isset($this)) {
	require_once __DIR__ . '/../conf/core/require.php';
	$kernel = new \Schoolbox\Application\WebRequest\Kernel();
	error(2);
	exit;
}
/** @var SitePage $this */

$this->setFile(TEMPLATE_content, 'messages.html');

$this->setBlock(TEMPLATE_content, 'no_messages', 'no_messages_block');
$this->setBlock(TEMPLATE_content, 'days', 'days_block');
$this->setBlock('days', 'day', 'day_block');
$this->setBlock('day', 'events', 'events_block');
$this->setBlock('events', 'event', 'event_block');
$this->setBlock('event', 'abstract', 'abstract_block');

$this->setTitle('All Messages');

$factory = new ListFactory(INSTANCE_ID, Redis::getClientInstance());

// load the data into your hat, and pull out a rabbit
$decorator = new Decorator();
$factory->listed($_SESSION['id'])->fetchIntoBundle($decorator);
$listed = $decorator->renderAsSitePageArray();

$decorator = new Decorator();
$factory->unread($_SESSION['id'])->fetchIntoBundle($decorator);
$unread = $decorator->renderAsSitePageArray();

$msgsByDay = array();

foreach($listed as $id => $notification) {
    $notification['unread'] = isset($unread[$id]);
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
		$fday = date('D M jS Y', strtotime($day));
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
                'EVENT_CLASS'    => $n['unread'] ? 'unread' : ''
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

if(count($listed)) {
	$this->parse('days_block', 'days');
    $this->setVar(array(
        'no_messages_block' => ''
    ));
}
else {
    $this->setVar(array(
        'days_block' => ''
    ));

    $this->parse('no_messages_block', 'no_messages');
}


