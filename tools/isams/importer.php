#!/usr/bin/php
<?php
/**
 * ISAMS Importer
 *
 * Imports from an xml file and populates the following tables:
 *  user_profile
 *  user_relation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon;

$appOpts = [
    'help',
];

require __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'ISAMS Importer',
    [
        Daemon\Kernel::OPTION_LEVEL         => Monolog\Logger::CRITICAL,
        Daemon\Kernel::OPTION_ERRORS        => E_ALL,
        Daemon\Kernel::OPTION_APP_LONG_OPTS => $appOpts,
    ]
);

/** @noinspection PhpUnhandledExceptionInspection */
$kernel->run(function (
    Di\Container $di,
    /** @noinspection PhpUnusedParameterInspection */
    LoopInterface $loop,
    LoggerInterface $log,
    array $opts
) {

    /* Help
     */
    if (isset($opts['help'])) {
        echo <<<HELP

Imports user data from an ISAMS batch xml source.

Usage: php import.php 
    
    
HELP;

        exit(0);
    }

    /* Throw all the strats in here
     */
    $registry = [];

    $userMapper = \Schoolbox\UserMapper::getInstance();

    pcntl_signal(SIGCHLD, SIG_DFL);

    $lock = new Alaress_Lock('isams_import.' . INSTANCE_ID . '.lock');

    if (!$lock->acquire()) {
        $log->critical('An ISAMS import is currently in progress, exiting...');
        exit(0);
    }

    $log->info("Beginning import of isams data");

    /* Execute each strat's import() method
     */
//    foreach ($registry as $strategy) {
//        $strategy->import();
//    }

    $lock->release();
});
