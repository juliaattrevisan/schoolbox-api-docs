<?php
use Schoolbox\Notif\Daemon\MQLauncher;
use Schoolbox\UserMapper;

/**
 * The Message Queue Daemon.
 *
 * Usage: php mq/mqd.php [-v][--deaf] &
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// enforce CLI only
if(isset($_SERVER['REQUEST_METHOD'])) {
	die("This script is to be run from commandline.\n");
}

require_once('../../include/Bootstrap.php');

// get verbosity
$options = getopt('v', array('deaf'));
$verbose = isset($options['v']) ? !$options['v'] : false;
$listen = !isset($options['deaf']);

$launcher = new MQLauncher($verbose ? STDOUT : null);

// if the server fails to start, bug out
if(!$launcher->startServer()) {
	exit;
}

// get a list of all SB users
$users = UserMapper::getInstance()->findAll();
$users = UserMapper::getInstance()->findByColumn('id', '151');

// create queues, bug out if it fails
if(!$launcher->spool($users)) {
	exit;
}

// start listening. blocking method.
if($listen) {
	$launcher->listen();
}

exit;
