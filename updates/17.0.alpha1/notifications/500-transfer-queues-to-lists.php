<?php
/**
 *
 * This script takes the contents of every user's unread queue
 * and transfers it into an equivalent redis list.
 * The queue is then deleted.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Async\AMQP\AMQPConnector;
use Alaress\Async\AMQP\Bunny\BunnyControl;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Queue\RedisQueue;
use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\IConfigLoader;

$opts = require_once __DIR__ . '/../../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Routing', array(
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'errors'  => E_ALL,
    'fields'  => array_keys($opts),
));

$kernel->run(function(Di\Container $di, LoopInterface $loop, Logger $log) {

    /** @var AMQPConnector $amqp */
    $amqp  = $di->get('amqp');

    /** @var Redis|ClientInterface $redis */
    $redis = $di->get('redis');

    /** @var IConfigLoader $conf */
    $conf  = $di->get('config');

    $ctrl  = new BunnyControl([
        'host'     => $conf->get('amqp_host'),
        'port'     => $conf->get('amqp_admin_port'),
        'username' => $conf->get('amqp_user'),
        'password' => $conf->get('amqp_pass'),
        'vhost'    => $conf->get('amqp_vhost')
    ]);

    $queues = $ctrl->listQueues([ BunnyControl::QUEUE_NAME, BunnyControl::QUEUE_MSGS ]);

    foreach ($queues as $queueData) {

        $queueName   = $queueData[ BunnyControl::QUEUE_NAME ];
        $queueLength = $queueData[ BunnyControl::QUEUE_MSGS ];

        if (!preg_match('`(\d+)\.unread$`', $queueName, $matches)) {
            // only want unread stuff
            continue;
        }

        $userId = $matches[1];

        if ($queueLength > 0) {
            $log->notice("Processing $queueLength messages on `$queueName`, user ID identified as $userId", [ 'char' => '<' ]);

            $listKey = sprintf('msg:%s:q:%d:unread', INSTANCE_ID, $userId);

            $list = new RedisQueue($redis, $listKey);

            // rip it out of rabbit and ram it into redis
            while ($msg = $amqp->readFromQueue($queueData[ BunnyControl::QUEUE_NAME ])) {
                try {
                    $decoded  = json_decode($msg->body, true);
                    $instance = Instance::jsonUnserialize($decoded['message']);

                    $log->debug("Pushing {$instance->getBaseHash()}:{$instance->getParamHash()} onto `$listKey`");
                    $list->push($instance);

                    $log->info("Deleting {$instance->getBaseHash()}:{$instance->getParamHash()} from `$queueName`", [ 'char' => '-' ]);

                } catch (InvalidArgumentException $e) {
                    $log->warning("Invalid message payload, deleting message");
                }

                // goodbye mr anderson
                $amqp->acceptMessage($msg);
            }

            // queue is now drained, obsolete, ...
            $log->notice("Queue `$queueName` drained; dropping queue", [ 'char' => '-' ]);

        } else {
            // queue is empty...
            $log->notice("Queue `$queueName` already empty; dropping queue", [ 'char' => '-' ]);
        }

        $amqp->deleteQueue($queueName);
        // ...and gone
    }

});
