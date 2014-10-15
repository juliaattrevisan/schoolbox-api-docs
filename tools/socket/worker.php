#!/usr/bin/php
<?php


/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$fields = array('porcelain', 'mem', 'truemem', 'pid');

$opts = getopt('v', $fields);

foreach($fields as $k => $name) {
    if(!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Worker', array(
    'errors' => E_ALL,
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'fields'  => array_values($fields),
));

$kernel->run(
       function ($di, $loop, $log, $options) {

           // use the default strat
           $strat = new \Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy();

           // we open stream(3) as our data output stream
           $writer = new \Alaress\WorkerCrew\Worker\PipeWriter($strat, $log, fopen("php://fd/3", 'w'));

           // grab external connector instances
           $redis  = new \Alaress\Redis();
           $client = $redis->getClientInstance();
           $amqp   = \Schoolbox\Notif\MQ\Connector::getInstance();

           $qf = new \Schoolbox\Notif\UserQueue\UserQueueFactory();
           $qf->setAMQP($amqp)
              ->setRedis($client)
              ->setKind(\Schoolbox\Notif\UserQueue\UserQueueFactory::UQ_INSTANT);

           // create a new worker; we don't need to store the instance, it'll be held within the loop
           new \Schoolbox\Realtime\Pipe\WorkerProcess($loop, $log, $writer, $strat, $qf);
       }
);

// yup. that's all you need to do.