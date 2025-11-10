#!/usr/bin/env php
<?php

/**
 * Inspect all Learnosity imported quizzes,
 * and repair those which contain no items, and the title doesn't match what we have in sbx
 *
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool\App;

require_once __DIR__ . '/../../../conf/core/require.php';

$sudo         = 'sudo -u ' . escapeshellarg(App::WEB_PROCESS_USER);
$importScript = 'php ' . __DIR__ . '/../../../tools/learnosity/repairBroken.php';
$logFile      = '/var/log/schoolbox/' . INSTANCE_ID . '/learnosity-import.log';

// Can't pipe whilst retaining sudo user AFAIK:
// need to tee and send output to null
$tee      = $sudo . ' tee -a ' . escapeshellarg($logFile) . ' > /dev/null';
$command  = $sudo . ' ' . ($importScript . ' --repair --perform 2>&1 | ' . $tee);

// Run the whole thing in background
echo "Running Learnosity repair command in background process:\n" . $command . "\n";
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
printf("Running Learnosity Repair (pid: %d)\n", $procStatus['pid']);

// Exit: the import will continue to run in background
exit();
