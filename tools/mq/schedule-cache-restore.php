#!/usr/bin/php
<?php

/**
 * Restore the Schedule cache, held in Redis, back into RabbitMQ
 * IF the schedule queue ALREADY exists in RabbitMQ, this script is to do nothing.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Async\Broker\StompRedis\Broker;
use Alaress\Redis;
use Alaress\Scheduler\Cache\RedisCache;
use Psr\Container\ContainerInterface;
use Schoolbox\Notif\MQ\Connector;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Notif\MQ\ConnectorFactory;
use Schoolbox\Realtime\Schedule;
use Schoolbox\Application\Daemon;

require_once __DIR__ . '/../../conf/core/require.php';
$k = new Daemon\Kernel(
    'Schedule Reload-from-Cache',
    [
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

try {
    $k->run(function ($di, $loop, $log, $opts) {
        /**
         * @var ContainerInterface $di
         * @var LoopInterface $loop
         * @var LoggerInterface $log
         * @var array $opts
         */

        // the passive part is important:
        // it means the queue won't be created if it doesn't already exist
        $mq = (new ConnectorFactory($di->get('config')))->build(['passive' => true]);
        $q  = Broker::QUEUE;

        try {
            $mq->declareQueue($q);

            // the queue exists. everything is ok. we are to do nothing.
            $log->notice("Queue '{$q}' already exists. Nothing to do.");
            exit(0);
        } catch (RuntimeException $e) {
            // the queue does NOT exist. this means we need to create it, then populate it with whatever is in Redis
            $log->notice("Queue '{$q}' does not exist; creating and re-populating from cache.", [ 'char' => '+' ]);

            try {
                // get the cache handler
                $jit = new RedisCache(Redis::getStorageClientInstance(), $log, INSTANCE_ID);

                /** @var Connector $amqp */
                $amqp = $di->get('amqp');

                // restore data if possible
                $restored = $jit->restoreSchedule($amqp, $q);

                if (!$restored) {
                    throw new RuntimeException("Could not read cached data");
                }

                $log->notice("Successfully restored Schedule queue from cache");
                exit(0);
            } catch (Exception $e) {
                $log->alert("Failed to restore Schedule queue from cache", ['exception' => $e]);
                exit(1);
            }
        }
    });
} catch (\Exception $e) {
}
