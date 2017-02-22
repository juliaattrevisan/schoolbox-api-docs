#!/usr/bin/php
<?php

use Alaress\WorkerCrew\Worker\PipeWriter;
use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Notif\Notifactory;
use Schoolbox\Notif\Queue\QueueFactory;
use Schoolbox\Notif\UserQueue\UserQueueFactory;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\Rerouter\QueueSorter;
use Schoolbox\Realtime\Routing;
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

foreach ($fields as $k => $name) {
    if (!isset($opts[$name])) {
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

$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    /** @var ClientInterface $client */
    $client = $di->get('redis');

    $uqf = new UserQueueFactory();
    $uqf->setKind(UserQueueFactory::UQ_REROUTE)
        ->setRedis($client);

    $qf = new QueueFactory();
    $qf->setRedis($client);

    // Add the messaging enabled filter
    $config = $di->get('config');

    // obtain filter list
    $filterFactory = new Filter\FilterFactory($uqf, $config);
    $rerouteFilter = $filterFactory->build();

    $notif = Notifactory::create();
    $notif->setLogger($log);

    $sorter = new QueueSorter($rerouteFilter, $log, $notif, $qf, RoutePermissionMapper::getInstance());

    // use the reroute strat
    $strat = new Routing\DispatchStrategy();

    // we open stream(3) as our data output stream
    $writer = new PipeWriter($strat, $log, fopen("php://fd/3", 'w'));

    new Routing\Worker($sorter, $loop, $log, $writer, $strat);
});

// yup. that's all you need to do.
