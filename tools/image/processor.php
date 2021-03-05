#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);
declare(ticks=1);

use Alaress\Image;

require_once __DIR__ . '/../../conf/core/require.php';

const DEFAULT_TIME_INTERVAL_MINUTES  = 60;                   // default time period in minutes to look up images for processing
const DEFAULT_IMAGE_COUNT_LIMIT      = 300;                  // default maximum amount of images to be processed
const DEFAULT_ATTEMPTS_PER_TRANSFORM = 3;                    // default maximum times to attempt each transformation

$timeInterval         = (int)getenv('TIME_INTERVAL', true) ?: DEFAULT_TIME_INTERVAL_MINUTES;
$workerCount          = (int)getenv('MAX_WORKERS', true) ?: (int)shell_exec('nproc');
$imageCountLimit      = (int)getenv('LIMIT', true) ?: DEFAULT_IMAGE_COUNT_LIMIT;
$retryFailed          = (bool)getenv('RETRY_FAILED', true);
$attemptsPerTransform = (int)getenv('MAX_ATTEMPTS', true) ?: DEFAULT_ATTEMPTS_PER_TRANSFORM;

$opt = getopt('hv', ['help', 'verbose']);
if (array_key_exists('h', $opt) || array_key_exists('help', $opt)) {
    echo <<<HELP

Convert image files into their commonly-accessed transforms. This preconversion trades time for storage
so that first-time-accesses don't require an on-demand transform which can sometimes slow the system
down when everyone demands image transforms simultaneously.

Usage: tools/image/processor.php

Options:
    -h,--help           Print this help message and exit
    -v,--verbose        Log with additional verbosity. Can be repeated up to 3 times.

Environment variables:
    TIME_INTERVAL=N     Produce standard transforms for storages files uploaded in last N minutes. Default: 60
    MAX_WORKERS=N       Maximum number of concurrent workers. Defaults to CPU count
    LIMIT=N             Maximum number of storage files to transform
    RETRY_FAILED=0|1    If true, just retry previously failed transforms. Eg if hash file has now been restored.
    MAX_ATTEMPTS=N      Maximum number of times to attempt a transformation if it throws a catchable exception

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
$parent = new Image\Processor\Manager(
    $timeInterval,
    $imageCountLimit,
    $workerCount,
    new Alaress\Lumberjack\Logger(STDOUT, STDERR, $limits[$verboseFlagCount]),
    $retryFailed,
    $attemptsPerTransform
);
$parent->run();
