#!/usr/bin/php
<?php
/**
 * The Schedule Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once('../../include/Bootstrap.php');

$opts = getopt('v');

$verbose = isset($opts['v']);

if($verbose) {
	echo <<<EOL

 ||
 || Schoolbox Schedule Daemon
 ||


EOL;
}

$verboser = new \Schoolbox\Realtime\Verboser($verbose ? STDOUT : null);

$loop = \React\EventLoop\Factory::create();

// CREATE SCHEDULER
$sched = new \Schoolbox\Realtime\Schedule\Scheduler($loop, $verboser, new \Schoolbox\ConfigLoader(), true);

$loop->run();

$verboser->v("Schedule daemon terminating.");