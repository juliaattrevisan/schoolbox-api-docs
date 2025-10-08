#!/usr/bin/php
<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Di\Configurator;
use Schoolbox\MultiInstance\RedisClientRepository;
use Schoolbox\Application\Daemon;
use Schoolbox\Realtime\MessageList\MultiInstance\ListFactory;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Daemon\Kernel("Worker", [Daemon\Kernel::OPTION_ERRORS => E_ALL]);

$kernel->run(function (ContainerInterface $di, LoopInterface $loop, LoggerInterface $log, array $opts): void {

    // use the default strat
    $strat = new \Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy();

    // we open stream(3) as our data output stream
    $writer = new \Alaress\WorkerCrew\Worker\PipeWriter($strat, $log, fopen('php://fd/3', 'wb'));

    /** @var RedisClientRepository $redisClientRepository */
    $redisClientRepository = $di->get('multiInstance.redisStorageClientRepository');

    $listFactory = new ListFactory($redisClientRepository);

    $configurator = new Configurator($di);

    // create a new worker; we don't need to store the instance, it'll be held within the loop
    new \Schoolbox\Realtime\Pipe\WorkerProcess($loop, $log, $writer, $strat, $listFactory, $configurator);
});

// yup. that's all you need to do.
