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

$w = new \Schoolbox\Realtime\Pipe\WorkerProcess(null, new Verboser(fopen("proc_".getmypid().".log", 'w')));
$w->run();

// yup. that's all you need to do.