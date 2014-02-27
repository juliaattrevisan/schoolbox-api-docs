#!/usr/bin/php
<?php
use React\Stream\Stream;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Queue\UserQueueFactory;
use Alaress\WorkerCrew\Pooling\DefaultStrategy;
use Schoolbox\Realtime\Verboser;

/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once "mini-bootstrap.php";

$loop = \React\EventLoop\Factory::create();

// if we use this strat, we only have to make changes once to affect server and workers
$strat = new DefaultStrategy();

$v = new Verboser(fopen("proc_".getmypid().".log", 'w'));

// listen to STDIN for commands
$stream = new Stream(STDIN, $loop);

$mq = new Connector();

$stream->on('data', function($data, $stream) use ($v, $strat, &$mq) {
	try {
		// sigterm?
		if(strlen($data) == 1 && $data[0] == $strat::STR_CANCEL) {
			exit;
		}

		try {
			list($cmd, $args) = $strat->decomposeTaskString($data);
		}
		catch(InvalidArgumentException $e) {
			$v->v("Rejecting data '$data'", '*');
			echo $strat::STR_NACKNOWLEDGE;
			return;
		}

		$data = json_decode($args, true);

		// if not a supported command, reject
		if(!in_array($cmd, $strat->getSupportedActions())) {
			$v->v("Rejecting command '$cmd'", '*');
			echo $strat::STR_NACKNOWLEDGE;
			return;
		}

		list($userId, $count) = array_values($data);

		// strategise when this worker does more than FETCH
		$messages = array();

		// get a queue to retrieve read messages synchronously
		$mqf = new UserQueueFactory($mq);

		$queue = $mqf->buildUserQueue($userId, $count, false);
		$allMessages = $queue->getMessages($count);

		foreach($allMessages as $read) {
			$messages[$read->getId()] = $read->getBody();
		}

		echo $strat->formatTransmissionData($messages);
	}
	catch(Exception $e) {
		// all exceptions must be caught, otherwise the react loop explodes and this worker dies
		$v->v("Worker exception: ".get_class($e)." ".$e->getMessage());
	}
});

$loop->run();