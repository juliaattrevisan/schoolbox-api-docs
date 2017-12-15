#!/usr/bin/php
<?php

/**
 * User Provisioning System command line entry point
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Lumberjack\CompositeLoggerInterface;
use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Sync\Provisioner\Provisioner;
use Schoolbox\Notif\Queue\Bindings;
use Schoolbox\Realtime\Routing\Cache\Redis\WriteCache;
use Schoolbox\Realtime\Routing\Subscription\Loader\Factory;
use Schoolbox\UserMapper;


$options = getopt("vr:", array('perform-update', 'instance::'));

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('UPS', [
    'verbose'  => isset($options['v']) ? $options['v'] : null,
    'perform'  => isset($options['perform-update']),
    'roleType' => (isset($options['r']) && in_array($options['r'], [ 'staff', 'student', 'parent' ])) ? $options['r'] : null,
    'instance' => isset($options['instance']) ? $options['instance'] : null
]);

// Signal handling unique to the provisioner
// (ticks required for pcntl_signal functions to work)
declare(ticks = 1);
$cleanup = function($signo) use ($kernel) {
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
    function ($di, $loop, $log, $opts) {
        $instanceId = isset($opts["instance"]) ? $opts["instance"] : INSTANCE_ID;

        /**
         * @var Di\Container    $di
         * @var LoopInterface   $loop
         * @var LoggerInterface $log
         * @var array           $opts
         */

        // define the redis key we'll use for this run
        $di->values['ups.redisKey'] = $instanceId . '.provision.user.' . getmypid();

        /** @var CompositeLoggerInterface $logger */
        $logger = $di->get('ups.logger');

        // append the CLI logger to the main logger
        $logger->pushComposite($log);

        /** @var Provisioner $ups */
        $ups = $di->get('ups.provisioner');

        // Note that trying to overwrite the logger (and thus destroy the old one)
        // will cause an exit as soon as stdout is written
        // (because stdout is closed when the old logger is destroyed)
        $ups->run($opts['perform'], $opts['roleType']);


        if ($opts['perform']) {
            // we need to manually refresh the routing cache now
            $gen   = new Bindings\Generator();
            /** @var ClientInterface $redis */
            $redis = $di->get('redis');

            $factory = (new Factory($redis, $log))
                ->setGenerator($gen)
                ->setUserMapper($di->get('mapperFactory')->get(UserMapper::class));

            $cache = new WriteCache($redis, $log, $factory);

            $cache->cacheAllUsers($instanceId);
        }
    }
);
