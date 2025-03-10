#!/usr/bin/php
<?php

/**
 * Lazy way to enable/disable external markbook sync
 *
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Config\ConfigMapper;

// Setup kernel
require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Schoolbox\Application\Daemon\Kernel('Enable Markbook External Sync');

// Get this kernel started

$kernel->run(function ($di, $loop, $log, $opts) {
    /**
     * Deprecation notice
     */
    echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
    echo "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m" . PHP_EOL;
    echo "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m" . PHP_EOL;
    echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
    echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;

    $configMapper = ConfigMapper::getInstance();
    $configMapper->getDatabase()->query("UPDATE config SET value = 0 WHERE name = 'external_markbook'");

    /**
     * Deprecation notice
     */
    # Deprecation notice
    echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
    echo "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m" . PHP_EOL;
    echo "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m" . PHP_EOL;
    echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
    echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
});
