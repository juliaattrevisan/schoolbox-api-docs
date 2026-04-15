#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool\App;

require_once __DIR__ . '/../../../conf/core/require.php';

$scriptName = realpath(__DIR__ . '/../../../tools/redis/migrateRefreshTokens.php');
$script     = 'php ' . escapeshellarg($scriptName) . ' -p';

$sudo    = 'sudo -u ' . escapeshellarg(App::WEB_PROCESS_USER);
$logFile = '/var/log/schoolbox/' . INSTANCE_ID . '/migrate-refresh-tokens.log';

// Can't pipe whilst retaining sudo user AFAIK:
// need to tee and send output to null
$tee     = $sudo . ' tee -a ' . escapeshellarg($logFile) . ' > /dev/null';
$command = $sudo . ' ' . ($script . ' --perform 2>&1 | ' . $tee);

// Run the whole thing in background
echo "Running migrate refresh tokens script as background process:\n$command\n";
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
printf("Beginning migrate refresh tokens script (pid: %d)\n", $procStatus['pid']);

// Exit: the process will continue to run in background.
exit;
