<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Mapper;
use Schoolbox\Resource\LogMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Filter;

$opts = require __DIR__ . '/../../conf/core/require-cli.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Redis Notif Cleanup',
    [
        'verbose' => [false, false],
        'errors'  => E_ALL,
    ]
);

$kernel->run(function(Di\Container $di, LoopInterface $loop, Logger $log, array $opts) {

    $redis = \Alaress\Redis::getClientInstance();

    $keys = $redis->keys(sprintf('msg:%s:e:*', INSTANCE_ID));

    $log->notice('Parsing ' . count($keys) . ' records');

    $deletions = [];

    foreach ($keys as $key) {
        $val = $redis->get($key);
        $len = strlen($val);
        $log->info(sprintf('[MATCH] %s (%d bytes) matched', $key, $len));
        $redis->del($key);
        $deletions[$key] = $len;
    }

    $log->notice(sprintf(
        'Total reclamation: %d keys accounting for %s',
        count($deletions),
        Alaress_Byte_Format::bytesToString(array_sum($deletions)))
    );

    $log->notice('Data deletion successful');

});