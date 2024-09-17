#!/usr/bin/php
<?php

/**
 * Fix passwords matching the invalid value "donotchange" in the background.
 *
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

require_once __DIR__ . '/../../conf/core/require.php';

// Run the script on storage_dir only, with no lock
// (so that existing crons do not raise warnings)
$script  = __DIR__ . '/../../../tools/user/invalidate-passwords.php';
$logFile = '/var/log/schoolbox/' . INSTANCE_ID . '/invalidate-passwords.log';
$command = $script . ' 2>&1 > ' . escapeshellarg($logFile);

// Run the whole thing in background
echo "Running invalidate passwords command in background process:\n" . $command . "\n";
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
printf("Running invalidate passwords (pid: %d)\n", $procStatus['pid']);

// Exit: the process will continue to run in background
exit();
