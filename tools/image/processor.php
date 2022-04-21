#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Image;
use Predis\ClientInterface;
use Schoolbox\Application\Daemon\Kernel;

require_once __DIR__ . '/../../conf/core/require.php';

// TODO move this to a CLI tool instead of using the daemon kernel
$kernel = new Kernel(__FILE__);

const DEFAULT_TIME_INTERVAL_MINUTES  = 60;                   // default time period in minutes to look up images for processing
const DEFAULT_IMAGE_COUNT_LIMIT      = 300;                  // default maximum amount of images to be processed
const DEFAULT_ATTEMPTS_PER_TRANSFORM = 3;                    // default maximum times to attempt each transformation

$opt                  = getopt('hv', ['help', 'verbose', 'retry-failed', 'time-interval:', 'max-workers:', 'limit:', 'max-attempts:']);
$retryFailed          = array_key_exists('retry-failed', $opt);
$timeInterval         = array_key_exists('time-interval', $opt) ? (int)$opt['time-interval'] : DEFAULT_TIME_INTERVAL_MINUTES;
$workerCount          = array_key_exists('max-workers', $opt) ? (int)$opt['max-workers'] : (int)max(1, ((int)shell_exec('nproc') * 0.75));
$imageCountLimit      = array_key_exists('limit', $opt) ? (int)$opt['limit'] : DEFAULT_IMAGE_COUNT_LIMIT;
$attemptsPerTransform = array_key_exists('max-attempts', $opt) ? (int)$opt['max-attempts'] : DEFAULT_ATTEMPTS_PER_TRANSFORM;

if (array_key_exists('h', $opt) || array_key_exists('help', $opt)) {
    echo <<<HELP

Convert image files into their commonly-accessed transforms. This preconversion trades time for storage
so that first-time-accesses don't require an on-demand transform which can sometimes slow the system
down when everyone demands image transforms simultaneously.

Usage: tools/image/processor.php

Options:
    -h,--help           Print this help message and exit
    -v,--verbose        Log with additional verbosity. Can be repeated up to 3 times.
    --retry-failed      If true, just retry previously failed transforms. Eg if hash file has now been restored.
    --time-interval N   Produce standard transforms for storages files uploaded in last N minutes. Default: 60
    --max-workers N     Maximum number of concurrent workers. Defaults to CPU count
    --limit N           Maximum number of storage files to transform
    --max-attempts N    Maximum number of times to attempt a transformation if it throws a catchable exception

HELP;
    exit;
}
proc_nice(10); // Always run with lower priority
$verboseFlagCount = 0;
foreach (['v', 'verbose'] as $verboseFlag) {
    if (array_key_exists($verboseFlag, $opt)) {
        $verboseFlagCount += count((array)$opt[$verboseFlag]);
    }
}
$verboseFlagCount = min($verboseFlagCount, 3);
$limits           = [
    0 => Monolog\Logger::ERROR,    // nil verbosity: exceptional behaviour only
    1 => Monolog\Logger::NOTICE,   // min verbosity: significant events
    2 => Monolog\Logger::INFO,     // mod verbosity: most things
    3 => Monolog\Logger::DEBUG,    // max verbosity: EV-ER-Y-THING...
];

// TODO inject lock inspector instaed of cache
/** @var ClientInterface $redis */
$redis  = $kernel->get('redis.cache');
$parent = new Image\Processor\Manager(
    $timeInterval,
    $imageCountLimit,
    $workerCount,
    new Alaress\Lumberjack\Logger(STDOUT, STDERR, $limits[$verboseFlagCount]),
    $retryFailed,
    $attemptsPerTransform,
    $limits[$verboseFlagCount],
    $redis
);
$isInterrupted = $parent->run();
exit($isInterrupted ? 1 : 0);
