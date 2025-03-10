#!/usr/bin/php
<?php

/**
 * Run three redis clean up scripts.
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
echo "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m" . PHP_EOL;
echo "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m" . PHP_EOL;
echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;

$logDir = "/var/log";
$logfile = 'clean-up-redis-' . date('Y-m-d') . '.log';
if (is_writable($logDir)) {
    $logfile = "{$logDir}/{$logfile}";
}

exec(__DIR__ . '/../../tools/mq/remove-old-triggers.php --perform -l=' . $logfile);
exec(__DIR__ . '/../../tools/mq/reset-ttls.php --perform -l=' . $logfile);
exec(__DIR__ . '/../../tools/notif/add-msg-to-object-queue.php --perform -l=' . $logfile);

echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
echo "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m" . PHP_EOL;
echo "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m" . PHP_EOL;
echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
