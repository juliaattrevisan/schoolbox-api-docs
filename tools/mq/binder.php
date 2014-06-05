#!/usr/bin/php
<?php

/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if(posix_getuid() !== 0) {
	die("[!] Try sudo.\n");
}

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$opts = getopt('vd');

$verbose = isset($opts['v']);

$dryRun = isset($opts['d']);

if($verbose) {
	echo <<<EOL

 ||
 || Schoolbox MessageQueue Binder
 ||


EOL;
}

$start = microtime(true);

$uMap = \Schoolbox\UserMapper::getInstance();

$userCollection = $uMap->findAll();

$qFactory = new \Schoolbox\Notif\Queue\UserQueueFactory();

$userCount = $userCollection->count();
$countLen = strlen("$userCount");
$i = 0;

foreach($userCollection as $user) {

	/** @var \Schoolbox\User $user */

	// hydrate
	$user = $uMap->findById($user->getId());

	if($verbose) {
		printf(
			"[%13.8f][%0{$countLen}d / %0{$countLen}d] Rebinding queues for %-16s ",
			microtime(true) - $start, ++$i, $userCount, $user->getUsername()
		);
	}

	$deleted = $qFactory->destoryUserBindings($user->getId(), $dryRun);

	if($deleted < 0) {
		if($verbose) {
			echo "unable to destroy bindings.\n";
			continue;
		}
	}

	if($verbose) {
		printf("%02d removed", $deleted);
	}

	$uq = $qFactory->bindUserQueue($user, $dryRun);

	if($verbose) {
		printf("  %02d added\n", count($uq->getBindings()));
	}
}

if($verbose) {
	echo "[ ] Notifying scheduler.\n";
}

$cmdr = new \Schoolbox\Realtime\Schedule\SchedulerCommander();
$cmdr->adviseReload();

if($verbose) {
	echo "[ ] Done.\n";
}


