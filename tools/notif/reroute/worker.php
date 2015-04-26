#!/usr/bin/php
<?php

use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Queue\QueueFactory;
use Schoolbox\Notif\UserQueue\UserQueueFactory;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\Rerouter\QueueSorter;
use Schoolbox\Realtime\Rerouter\RerouterDispatchStrategy;
use Schoolbox\Realtime\Worker\RerouteWorker;
use Schoolbox\Realtime\Rerouter\Filter;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$fields = array('porcelain', 'mem', 'truemem', 'pid');

$opts = getopt('v', $fields);

foreach($fields as $k => $name) {
    if(!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Reroute Worker', array(
	'errors' => E_ALL,
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'fields'  => array_values($fields),

));

$kernel->run(function($di, $loop, $log, $opts) {
    /**
     * @var Di\Container $di
     * @var LoopInterface $loop
     * @var LoggerInterface $log
     * @var array $opts
     */

    $redis  = new \Alaress\Redis();
    $client = $redis->getClientInstance();

    $uqf = new UserQueueFactory();
    $uqf->setKind(UserQueueFactory::UQ_REROUTE)
        ->setRedis($client);

    $qf = new QueueFactory();
    $qf->setRedis($client);

	$rerouteFilters = array(
		new Filter\AlreadyReroutedFilter(),
		new Filter\SelfSendFilter(),
		new Filter\CampusMembershipFilter(),
		new Filter\RoleMembershipFilter(),
		new Filter\RoletypeFilter(),
		new Filter\DuplicateFilter($uqf)
	);

    $sorter = new QueueSorter($rerouteFilters, $log, new Notif(), $qf, RoutePermissionMapper::getInstance());

    // use the reroute strat
    $strat = new RerouterDispatchStrategy();

    // we open stream(3) as our data output stream
    $writer = new \Alaress\WorkerCrew\Worker\PipeWriter($strat, $log, fopen("php://fd/3", 'w'));

    new RerouteWorker($sorter, $loop, $log, $writer, $strat);
});

// yup. that's all you need to do.