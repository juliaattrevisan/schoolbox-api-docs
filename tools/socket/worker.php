#!/usr/bin/php
<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\MessageList\ListFactory;


/** @var array $fields */
$fields = [ 'porcelain', 'mem', 'truemem', 'pid' ];

$opts = getopt('v', $fields, $_);

foreach($fields as $k => $name) {
    if(!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel("Worker", [
    'errors' => E_ALL,
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'fields'  => array_values($fields),
]);

$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    // use the default strat
    $strat = new \Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy();

    // we open stream(3) as our data output stream
    $writer = new \Alaress\WorkerCrew\Worker\PipeWriter($strat, $log, fopen('php://fd/3', 'wb'));

    /** @var ClientInterface $redis */
    $redis = $di->get('redis');

    $listFactory = new ListFactory(INSTANCE_ID, $redis);

    /** @var Notif $notif */
    $notif = $di->get('notif');

    // create a new worker; we don't need to store the instance, it'll be held within the loop
    new \Schoolbox\Realtime\Pipe\WorkerProcess($loop, $log, $writer, $strat, $listFactory, $notif);
});

// yup. that's all you need to do.
