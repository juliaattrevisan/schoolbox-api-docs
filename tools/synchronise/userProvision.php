#!/usr/bin/php
<?php

/**
 * User Provisioning System command line entry point
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Lumberjack\CompositeLoggerInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Realtime\Routing\Cache\WritableCache;
use Schoolbox\Sync\Provisioner\Provisioner;
use Schoolbox\Application\Daemon;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Daemon\Kernel(
    'UPS',
    [
        Daemon\Kernel::OPTION_APP_SHORT_OPTS => 'r:',
        Daemon\Kernel::OPTION_APP_LONG_OPTS  => ['perform-update', 'instance::'],
    ]
);

// Signal handling unique to the provisioner
// (ticks required for pcntl_signal functions to work)
declare(ticks=1);
$cleanup = function ($signo) use ($kernel) {
    /** @var Provisioner $ups */
    $ups = $kernel->getDependencyInjector()->get('ups.provisioner');
    $ups->onInterrupt($signo);
    exit;
};
pcntl_signal(SIGINT, $cleanup);
pcntl_signal(SIGTERM, $cleanup);
pcntl_signal(SIGQUIT, $cleanup);
$kernel->registerExceptionCallback($cleanup);

$kernel->run(
    function (ContainerInterface $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

        // Ensure tool being run as www-data user
        $requiredUser = 'www-data';
        $actualUser   = posix_getpwuid(posix_geteuid())['name'];
        if ($actualUser !== $requiredUser) {
            $log->error("Invalid process user. Required '$requiredUser', Actual: '$actualUser'");
            $i = INSTANCE_ID;
            $path = realpath(__FILE__);
            $log->error("Maybe try something like:\n\tsudo -u $requiredUser INSTANCE_ID=$i $path");
            exit(2);
        }

        $perform    = isset($opts['perform-update']);
        $roleType   = (isset($opts['r']) && in_array($opts['r'], [ 'staff', 'student', 'parent' ])) ? $opts['r'] : null;
        $instanceId = $opts["instance"] ?? INSTANCE_ID;


        // AFAICT this is unused
        // $di->values['ups.redisKey'] = $instanceId . '.provision.user.' . getmypid();

        /** @var CompositeLoggerInterface $logger */
        $logger = $di->get('ups.logger');

        // append the CLI logger to the main logger
        $logger->pushComposite($log);

        /** @var Provisioner $ups */
        $ups = $di->get('ups.provisioner');

        // Note that trying to overwrite the logger (and thus destroy the old one)
        // will cause an exit as soon as stdout is written
        // (because stdout is closed when the old logger is destroyed)
        $ups->run($perform, $roleType);


        if ($perform) {
            // we need to manually refresh the routing cache now
            /** @var \Schoolbox\Realtime\Routing\Cache\Redis\WriteCache $cache */
            $cache = $di->get('subscriptionCache');
            $cache->setLog($log);
            $cache->cacheAllUsers($instanceId);
        }
    }
);
