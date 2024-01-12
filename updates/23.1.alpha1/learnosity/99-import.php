#!/usr/bin/php
<?php

/**
 * Migrate all known quizzes into learnosity land
 *
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool\App;

require_once __DIR__ . '/../../../conf/core/require.php';

/* FIXME but...this requires credentials in order to proceed
 *  and the credentials aren't populated until afterwards.
 *  That makes migration a two-step process, not one.
 */
$sudo         = 'sudo -u ' . escapeshellarg(App::WEB_PROCESS_USER);
$importScript = 'php ' . __DIR__ . '/../../../tools/learnosity/import.php';
$logFile      = '/var/log/schoolbox/' . INSTANCE_ID . '/learnosity-import.log';

// Can't pipe whilst retaining sudo user AFAIK:
// need to tee and send output to null
$tee      = $sudo . ' tee -a ' . escapeshellarg($logFile) . ' > /dev/null';
$commands = [
    $sudo . ' ' . ($importScript . ' --small --perform 2>&1 | ' . $tee),
    $sudo . ' ' . ($importScript . ' --large --perform 2>&1 | ' . $tee),
];
$command  = implode(' && ', $commands);

// Run the whole thing in background
echo "Running Learnosity import commands in background process:\n" . implode("\n", $commands) . "\n";
$proc       = proc_open(
    "($command)",
    [
        0 => STDIN,
        1 => STDOUT,
        2 => STDERR,
    ],
    $pipes,
);
$procStatus = proc_get_status($proc);
printf("Beginning Learnosity import (pid: %d)\n", $procStatus['pid']);

// Exit: the import will continue to run in background
exit();
