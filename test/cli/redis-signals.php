#!/usr/bin/php
<?php

/**
 * The intent of this script is to test signal handling based on Redis pubsub.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Redis;
use Alaress\Test\Redis\Async\TestListener;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon;

$appShortOpts = 'd';
$appLongOpts  = [ 'help', 'channel:', 'signal:', 'loop:' ];

$opts = getopt($appShortOpts, $appLongOpts);
if (isset($opts['help'])) {
    echo <<<EOL

Use -d to run in daemon (subscribe) mode, otherwise you are in client (publish) mode

EOL;
    exit;
}

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Redis pubsub signal test',
    [
        Daemon\Kernel::OPTION_LEVEL          => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_APP_SHORT_OPTS => $appShortOpts,
        Daemon\Kernel::OPTION_APP_LONG_OPTS  => $appLongOpts,
    ]
);

if (!isset($opts['d'])) {
    // client (publish) mode
    $signal = isset($opts['signal'])  ? $opts['signal']  : strtoupper(readline('Signal (name) to send [SIGPOLL]: ') ?: 'sigpoll');
    $daemon = $opts['channel'] ?? (\readline('Daemon name [test]: ') ?: 'test');

    /** @var \Redis $redis */
    $redis = Redis::getSignalQueueClientInstance();

    $loop = isset($opts['loop']) ? (int)($opts['loop']) : 1;

    $recipientCounts = [];

    $a = microtime(true);
    for ($i = 0; $i < $loop; ++$i) {
        $recipientCounts[] = $redis->publish($daemon, $signal);
    }
    $b = microtime(true);

    $recipientCount = max($recipientCounts);

    $output = sprintf(
        "Sent %s on topic `%s`",
        $signal,
        $daemon
    );

    if ($loop > 1) {
        $output .= sprintf(" %d times in %0.5f seconds", $loop, ($b - $a));
    }

    if ($recipientCount == 0) {
        $output .= "... but nobody was listening.";
    } else {
        $output .= sprintf(
            ", received by %d subscriber%s.",
            $recipientCount,
            $recipientCount == 1 ? '' : 's'
        );
    }

    $kernel->logger()->debug($output);

    exit;
}


// daemon (subscribe / consume) mode
$kernel->run(function (ContainerInterface $di, LoopInterface $loop, LoggerInterface $log, array $opts): void {

    $daemon = $opts['channel'] ?? (\readline('Daemon name [test]: ') ?: 'test');

    /** @var Redis\Async\Client $client */
    $client = $di->get('redis.signalQueue.sub');
    $client->subscribe($daemon, new TestListener($log));
});
