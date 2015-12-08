#!/usr/bin/php
<?php

/**
 * User Provisioning System command line entry point
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Redis;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Notif\StoredisLogger;
use Schoolbox\Sync\Log;
use Schoolbox\Sync\Provisioner\Provisioner;

$options = getopt("v", array('perform-update'));

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('UPS', [
    'verbose' => isset($options['v']) ? $options['v'] : null,
    'perform' => isset($options['perform-update']),
]);


$kernel->run(
    function ($di, $loop, $log, $opts) {

        /**
         * @var Di\Container    $di
         * @var LoopInterface   $loop
         * @var LoggerInterface $log
         * @var array           $opts
         */

        // define the redis key we'll use for this run
        $di->values['ups.redisKey'] = INSTANCE_ID . '.provision.user.' . getmypid();

        /** @var StoredisLogger $logger */
        $logger = $di->get('ups.logger');

        // append the CLI logger to the main logger
        $logger->pushComposite($log);

        /** @var Provisioner $ups */
        $ups = $di->get('ups.provisioner');

        // Note that trying to overwrite the logger (and thus destroy the old one)
        // will cause an exit as soon as stdout is written
        // (because stdout is closed when the old logger is destroyed)
        $ups->run($opts['perform']);
    }
);
