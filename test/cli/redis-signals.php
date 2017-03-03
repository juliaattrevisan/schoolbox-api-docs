#!/usr/bin/php
<?php
/**
 * The intent of this script is to test signal handling based on Redis pubsub.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Redis;
use Alaress\Test\Redis\Async\TestListener;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

$opts = getopt('d', [ 'help', 'channel:', 'signal:', 'loop:' ]);

if (isset($opts['help'])) {
    echo <<<EOL

Use -d to run in daemon (subscribe) mode, otherwise you are in client (publish) mode

EOL;
    exit;
}

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$daemon = isset($opts['channel']) ? $opts['channel'] : (readline('Daemon name [test]: ') ?: 'test');

$kernel = new Schoolbox\Application\Daemon\Kernel('Redis pubsub signal test', [
    'verbose' => [ false, false, false ],
    'daemon'  => $daemon
]);


if (!isset($opts['d'])) {
    // client (publish) mode
    $signal = isset($opts['signal'])  ? $opts['signal']  : strtoupper(readline('Signal (name) to send [SIGPOLL]: ') ?: 'sigpoll');

    /** @var \Redis $redis */
    $redis = Redis::getClientInstance();

    $loop = isset($opts['loop']) ? (int)($opts['loop']) : 1;

    $recipientCounts = [];

    $a = microtime(true);
    for ($i = 0; $i < $loop; ++$i) {
        $recipientCounts[] = $redis->publish($daemon, $signal);
    }
    $b = microtime(true);

    $recipientCount = max($recipientCounts);

    $output = sprintf("Sent %s on topic `%s`",
        $signal, $daemon
    );

    if ($loop > 1) {
        $output .= sprintf(" %d times in %0.5f seconds", $loop, ($b - $a));
    }

    if ($recipientCount == 0) {
        $output .= "... but nobody was listening.";
    } else {
        $output .= sprintf(", received by %d subscriber%s.",
            $recipientCount, $recipientCount == 1 ? '' : 's'
        );
    }

    $kernel->logger()->debug($output);

    exit;
}


// daemon (subscribe / consume) mode
$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    $client = new Redis\Async\Client($di->get('config')->get('redis_url'), $loop);

    $client->subscribe($opts['daemon'], new TestListener($log));

});
