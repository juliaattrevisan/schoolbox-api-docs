#!/usr/bin/php
<?php

/**
 * Fix storage dir permissions in the background: set all of them so that they
 * are owned by `www-data:www-data`.
 *
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool\App;

require_once __DIR__ . '/../../../conf/core/require.php';

// Run the script on storage_dir only, with no lock
// (so that existing crons do not raise warnings)
$script  = __DIR__ . '/../../../tools/set-config-data-dirs-perms.sh';
$flags   = ' --no-lock' .
           ' --dir storage_dir' .
           ' --owner ' . escapeshellarg(App::WEB_PROCESS_USER) .
           ' --group ' . escapeshellarg(App::WEB_PROCESS_USER);
$logFile = '/var/log/schoolbox/' . INSTANCE_ID . '/set-storage-dir-perms.log';
$command = $script . ' ' . $flags . ' 2>&1 > ' . escapeshellarg($logFile);

// Run the whole thing in background
echo "Running set storage dir perms command in background process:\n" . $command . "\n";
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
printf("Running set storage dir perms (pid: %d)\n", $procStatus['pid']);

// Exit: the import will continue to run in background
exit();
