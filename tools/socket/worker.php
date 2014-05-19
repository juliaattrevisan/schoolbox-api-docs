#!/usr/bin/php
<?php

use Alaress\Cli\Debug;

// this gets routed to err.log by the parent process
error_reporting(E_ALL);

/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once "mini-bootstrap.php";

$w = new \Schoolbox\Realtime\Pipe\WorkerProcess(null, new Debug(fopen("proc.log", 'a')));
$w->run();

// yup. that's all you need to do.