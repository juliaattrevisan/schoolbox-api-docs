#!/usr/bin/php
<?php

/**
 * Migrate all known quizzes into learnosity land
 *
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../conf/core/require.php';

/* FIXME but...this requires credentials in order to proceed
 *  and the credentials aren't populated until afterwards.
 *  That makes migration a two-step process, not one.
 */
$importScript = 'php ' . __DIR__ .  '/../../../tools/learnosity/import.php';
$logFile      = '/var/log/schoolbox/' . INSTANCE_ID . '/learnosity-import.log';
$commands     = [
    $importScript . ' --small --perform >> ' . $logFile . ' 2>&1',
    $importScript . ' --large --perform >> ' . $logFile . ' 2>&1',
];
$command      = implode(' && ', $commands);

// Run the whole thing in background
echo "Running Learnosity import commands in background process:\n" . implode("\n", $commands) . "\n";
passthru('(' . $command . ') & echo "Beginning Learnosity import (pid: ${!})"');
exit();
