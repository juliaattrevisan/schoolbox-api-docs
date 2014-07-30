#!/usr/bin/php
<?php

use Alaress\Cli\Debug;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Queue\UserQueueFactory;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\Rerouter\QueueSorter;
use Schoolbox\Realtime\Rerouter\RerouterDispatchStrategy;
use Schoolbox\Realtime\Worker\RerouteWorker;
use \Schoolbox\Realtime\Rerouter\Filter;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Reroute Worker', array(
	'errors' => E_ALL
));

$kernel->run(function($di, $loop, $debug, $options) {

	// reset debug so it gets sent properly back to the parent process' log
	$debug = new Debug(STDOUT, array('time','mem'));

	$rerouteFilters = array(
		new Filter\AlreadyReroutedFilter(),
		new Filter\SelfSendFilter(),
		new Filter\CampusMembershipFilter(),
		new Filter\RoleMembershipFilter(),
		new Filter\RoletypeFilter(),
		new Filter\DuplicateFilter(new UserQueueFactory())
	);

	$sorter = new QueueSorter($rerouteFilters, $debug, new Notif(), RoutePermissionMapper::getInstance());

	new RerouteWorker($sorter, $loop, new RerouterDispatchStrategy(), $debug);
});

// yup. that's all you need to do.