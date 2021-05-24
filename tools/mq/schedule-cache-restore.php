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

use Alaress\Async\Stomp\IClient;
use Alaress\Scheduler\Cache\RedisCache;
use Predis\ClientInterface;
use Schoolbox\Di\Configurator;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\MQ\Connector;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Realtime\Schedule;
use Schoolbox\Realtime\Schedule\Event\NotifierInflector;
use Schoolbox\Realtime\Schedule\Event\Splitter;
use Schoolbox\Realtime\Schedule\Trigger\Propagator;
use Schoolbox\Realtime\Schedule\Trigger\TriggerFactoryFactory;
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
         * @var Di\Container $di
         * @var LoopInterface $loop
         * @var LoggerInterface $log
         * @var array $opts
         */

        // the passive part is important: it means the queue won't be created if it doesn't already exist
        $mq = new Connector([
        'passive' => true
        ]);

        $q = 'sb.schedule';

        try {
            $mq->declareQueue($q);

            // the queue exists. everything is ok. we are to do nothing.
            $log->notice("Queue '{$q}' already exists. Nothing to do.");
            exit(0);
        } catch (RuntimeException $e) {
            // the queue does NOT exist. this means we need to create it, then populate it with whatever is in Redis
            $log->notice("Queue  '{$q}' does not exist; creating and re-populating from cache.", [ 'char' => '+' ]);

            try {
                // get the cache handler
                $jit = new RedisCache(Alaress\Redis::getClientInstance(), $log, INSTANCE_ID);
                $tff = new TriggerFactoryFactory(true);

                $splitter = new Splitter(new NotifierInflector(new Configurator($di)), $tff);

                /** @var Alaress\Async\AMQP\AMQPInterface $amqp */
                $amqp = $di->get('amqp');

                /** @var ClientInterface $redis */
                $redis = $di->get('redis');

                /** @var IClient $stomp */
                $stomp = $di->get('stomp');

                $broker = new Schedule\Broker(INSTANCE_ID, $amqp, $redis, $stomp);

                $propagator = new Propagator($splitter, $redis, $broker, INSTANCE_ID);

                // restore data if possible
                $restored = $jit->restoreSchedule(new Connector(), $propagator, $q);

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
