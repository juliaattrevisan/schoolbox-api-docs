#!/usr/bin/php
<?php

/**
 * Run maxzero-attempts.php to fix a problem with the max_mark value null on incomplete attempts.
 * See maxzero-attempts.php for more details.
 *
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool\App;

require_once __DIR__ . '/../../conf/core/require.php';

$sudo           = 'sudo -u ' . escapeshellarg(App::WEB_PROCESS_USER);
$maxzeroScript = 'php ' . __DIR__ . '/../../tools/learnosity/maxzero-attempts.php -p';
$logFile        = '/var/log/schoolbox/' . INSTANCE_ID . '/learnosity-maxzero-attempts.log';

// Can't pipe whilst retaining sudo user AFAIK:
// need to tee and send output to null
$tee     = $sudo . ' tee -a ' . escapeshellarg($logFile) . ' > /dev/null';
$command = $sudo . ' ' . ($maxzeroScript . ' --perform 2>&1 | ' . $tee);

// Run the whole thing in background
echo "Running Learnosity maxzero-attempts script as background process:\n$command\n";
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
printf("Beginning Learnosity maxzero-attempts script (pid: %d)\n", $procStatus['pid']);

// Exit: the process will continue to run in background.
exit();
