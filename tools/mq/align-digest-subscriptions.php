#!/usr/bin/php
<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Async\UserQueue\UserQueue;
use Alaress\Redis;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Notif\Queue\QueueFactory;
use Schoolbox\Realtime\Rerouter\Filter;
use Schoolbox\Realtime\Rerouter\UserSubscriptionList;
use Schoolbox\User;
use Schoolbox\UserMapper;


if (posix_getuid() !== 0) {
    die("[!] Try sudo.\n");
}

$fields = array('perform');

$opts = getopt('v', $fields);

foreach ($fields as $k => $name) {
    if (!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

$flags = implode(' ', array_slice($argv, 1));

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Digest-Subscription Alignment', array(
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'fields'  => array_values($fields),
    'flags'   => $flags,
    'dry-run' => isset($opts['dry-run'])
));

$kernel->run(function ($di, $loop, $log, $opts) {
    /**
     * @var Di\Container    $di
     * @var LoopInterface   $loop
     * @var LoggerInterface $log
     * @var array           $opts
     */

    $conf = $di->get('config');

    // redis dependencies
    $redis = Redis::getClientInstance();

    // database dependencies
    $uMap = UserMapper::getInstance();

    // new object dependencies
    $qf = new QueueFactory();
    $qf->setRedis($redis)
       ->setKind(UserQueue::Q_DIGEST);

    $subList   = new UserSubscriptionList($qf, $log);

    // import data from database
    $log->debug("Importing user data");
    $subList->importFromSource($uMap);
    $log->warning("Import complete");

    // final preflight details
    $userCount = count($subList->getAllQueueNames());

    if ($userCount == 0) {
        $log->error("No users found, no work to do. Exiting.");
        return;
    }

    $countLen  = strlen("$userCount");
    $pattern   = "Checking user %{$countLen}d of %{$countLen}d:  %s";

    $log->debug("Preparing to check digest data of $userCount users");

    $rerouteFilters = (new Filter\FilterFactory($qf, $conf))->build();

    $instanceId = INSTANCE_ID;

    foreach ($subList->getAllQueueNames() as $i => $queue) {
        /** @var User $user */
        $user = $subList->getQueueData($queue);

        // advise progress
        $log->notice(sprintf($pattern, $i + 1, $userCount, $user->getUsername()));

        $q = $qf->buildForUser($user->getId());
        $pendingMessages = $q->fetch();

        foreach ($pendingMessages as $instance) {

            $hash = $instance->getBaseHash() . ':' . $instance->getParamHash();

            $log->info("Processing message $hash", [ 'char' => '>' ]);

            if (count($instance->getTopics())) {
                $log->debug("Topic: " . implode(', ', $instance->getTopics()));
            } else {
                $log->info("No topic data on message", [ 'char' => '?' ]);
            }

            $formatted = ['context' => [] ];

            // reconstitute the context
            foreach ($instance->getContext() as $type => $values) {
                $formatted['context'][$type] = array_values(array_unique($values));
            }

            if (count($formatted['context'])) {
                $log->debug("Context: " . json_encode($formatted['context']));
            } else {
                $log->info("No context data on message", [ 'char' => '?' ]);
            }


            try {
                $rerouteFilters->validate($instance, $formatted, $user);

                // message passed validation - keep it (do nothing)
                $log->info("Message passed validation; user to retain.", [ 'char' => '+' ]);

            } catch (Filter\FilterException $e) {
                $log->info("Message failed validation: {$e->getMessage()}", [ 'char' => '-' ]);

                $name = $qf->buildForUser($user->getId())->getName();

                // message failed validation - drop it
                $log->debug("TO EXEC: redis-cli srem msg:$instanceId:q:$name msg:$instanceId:m:$hash");
            }

        }
    }

    $log->notice("Done.");
});
