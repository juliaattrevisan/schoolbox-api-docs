#!/usr/bin/env php
<?php

/**
 * Run maxmarks.php to fix a problem with the max_mark value on shorttext quiz questions.
 * See maxmarks.php for more details.
 *
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool\App;

require_once __DIR__ . '/../../../conf/core/require.php';

$sudo           = 'sudo -u ' . escapeshellarg(App::WEB_PROCESS_USER);
$maxmarksScript = 'php ' . __DIR__ . '/../../../tools/learnosity/maxmarks.php';
$logFile        = '/var/log/schoolbox/' . INSTANCE_ID . '/learnosity-maxmarks.log';

// Can't pipe whilst retaining sudo user AFAIK:
// need to tee and send output to null
$tee     = $sudo . ' tee -a ' . escapeshellarg($logFile) . ' > /dev/null';
$command = $sudo . ' ' . ($maxmarksScript . ' --perform 2>&1 | ' . $tee);

// Run the whole thing in background
echo "Running Learnosity maxmarks script as background process:\n$command\n";
$proc = proc_open(
    "($command)",
    [
        0 => STDIN,
        1 => STDOUT,
        2 => STDERR,
    ],
    $pipes,
);
$procStatus = proc_get_status($proc);
printf("Beginning Learnosity maxmarks script (pid: %d)\n", $procStatus['pid']);

// Exit: the process will continue to run in background.
exit();
