#!/usr/bin/php
<?php
/**
 * Restore the Schedule cache, held in Redis, back into RabbitMQ
 * IF the schedule queue ALREADY exists in RabbitMQ, this script is to do nothing.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Alaress\Scheduler\Cache\RedisCache;
use Predis\ClientInterface;
use React\Stomp\Client;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\MQ\Connector;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Realtime\Schedule;
use Schoolbox\Realtime\Schedule\Event\Splitter;
use Schoolbox\Realtime\Schedule\Trigger\Propagator;
use Schoolbox\Realtime\Schedule\Trigger\TriggerFactoryFactory;

$fields = array('porcelain', 'mem', 'truemem', 'pid');

$opts = getopt('v', $fields);

foreach($fields as $k => $name) {
    if(!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
$k = new \Schoolbox\Application\Daemon\Kernel('Schedule Reload-from-Cache', [
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'errors'  => E_ALL,
    'fields'  => array_values($fields),
]);


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

            /** @var IConfigLoader $conf */
            $conf = $di->get('config');

            $tff = new TriggerFactoryFactory(true);

            $splitter = new Splitter($conf, $di, $tff);

            /** @var Alaress\Async\AMQP\AMQPInterface $amqp */
            $amqp = $di->get('amqp');

            /** @var ClientInterface $redis */
            $redis = $di->get('redis');

            /** @var Client $stomp */
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
            $log->alert("Failed to restore Schedule queue from cache", ['exception'=>$e]);
            exit(1);
        }
    }
});



} catch (\Exception $e) {}