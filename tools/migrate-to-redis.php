<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Alaress\Async\AMQP\Bunny\BunnyControl;
use Alaress\Async\Queue\AMQPQueue;
use Alaress\Async\Queue\RedisQueue;
use Alaress\Async\UserQueue\UserQueue;
use Alaress\Redis;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\UserQueue\UserQueueFactory;

// check if we're in the harness or not
if (!class_exists('\Schoolbox\Application\Daemon\Kernel', false)) {
    require_once __DIR__ . '/../conf/core/require.php';

    spl_autoload_call('\\Schoolbox\\Application\\Daemon');
    new Schoolbox\Application\Daemon\Kernel('Migration from AMQP to Redis');
}


/**
 * Wrap in a function to protect context from the harness
 */
function migrateToRedis() {

    // stop le demons - manually!

    $amqp      = Connector::getInstance();
    $redis     = Redis::getClientInstance();
    $conf      = ConfigMapper::getInstance();
    $cache     = array();
    $bunny     = new BunnyControl([
        'host'     => $conf->get('amqp_host'),
        'port'     => $conf->get('amqp_admin_port'),
        'username' => $conf->get('amqp_user'),
        'password' => $conf->get('amqp_pass'),
        'vhost'    => $conf->get('amqp_vhost')
    ]);

    $pattern   = INSTANCE_ID . '\.u\.(\d+)\.([a-z]+)';
    $qFactory  = new \Schoolbox\Notif\Queue\QueueFactory();
    $uqFactory = new UserQueueFactory();

    $qFactory->setRedis($redis);
    $uqFactory->setRedis($redis)->setAMQP($amqp);

    foreach ($bunny->listQueues(array('name')) as $q) {
        if (preg_match("`$pattern`", $q['name'], $matches)) {
            $cache[$matches[1]][$matches[2]] = $q['name'];
        }
    }

    // iterate all users in Schoolbox
    foreach ($cache as $userId => $queues) {
        // we are looking for "digest" and "listed" queues
        foreach (array(UserQueue::Q_LISTED, UserQueue::Q_DIGEST) as $kind) {
            // if found...
            if (array_key_exists($kind, $queues)) {
                // the contents is to be moved to Redis, and...
                $queueName = $queues[$kind];

                $oldQ = new AMQPQueue($amqp, "$userId.$kind", '');
                $newQ = $qFactory->setKind($kind)->buildForUser($userId);

                foreach ($oldQ->fetch() as $instance) {
                    if ($newQ instanceof RedisQueue) {
                        $newQ->push($instance);
                    }
                }

                // the queue is to be deleted
                $amqp->deleteQueue($queueName);
            }
        }
    }
}

migrateToRedis();
