#!/usr/bin/php
<?php

/**
 * Run three redis clean up scripts.
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

$logDir = "/var/log";
$logfile = 'clean-up-redis-' . date('Y-m-d') . '.log';
if (is_writable($logDir)) {
    $logfile = "{$logDir}/{$logfile}";
}

exec(__DIR__ . '/../../tools/mq/remove-old-triggers.php --perform -l=' . $logfile);
exec(__DIR__ . '/../../tools/mq/reset-ttls.php --perform -l=' . $logfile);
exec(__DIR__ . '/../../tools/notif/add-msg-to-object-queue.php --perform -l=' . $logfile);
