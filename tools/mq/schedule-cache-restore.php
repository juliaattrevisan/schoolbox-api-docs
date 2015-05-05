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
use Schoolbox\Notif\MQ\Connector;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

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

    $q = INSTANCE_ID . '.sb.sched';

    try {
        $mq->declareQueue($q);

        // the queue exists. everything is ok. we are to do nothing.
        $log->notice("Queue already exists. Nothing to do.");
        exit(0);

    } catch (RuntimeException $e) {
        // the queue does NOT exist. this means we need to create it, then populate it with whatever is in Redis
        try {
            // get the cache handler
            $jit = new RedisCache(Alaress\Redis::getClientInstance(), $log, INSTANCE_ID);

            // restore data if possible
            $restored = $jit->restoreSchedule(new Connector());

            if (!$restored) {
                throw new RuntimeException("Could not read cached data");
            }

            $log->notice("Successfully restored Schedule queue from cache");
            exit(0);

        } catch (Exception $e) {
            $log->alert("Failed to restore Schedule queue from cache", [$e]);
            exit(1);
        }
    }
});