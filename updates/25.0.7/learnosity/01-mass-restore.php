<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool\App;

require_once __DIR__ . '/../../../conf/core/require.php';

/* Derived from 99-import.php implementation
 */
$sudo    = 'sudo -u ' . escapeshellarg(App::WEB_PROCESS_USER);
$script  = 'php ' . __DIR__ .  '/../../../tools/learnosity/archive-mass-restore.php';
$logFile = '/var/log/schoolbox/' . INSTANCE_ID . '/learnosity-mass-restore.log';

// Can't pipe whilst retaining sudo user AFAIK:
// need to tee and send output to null
$tee     = $sudo . ' tee -a ' . escapeshellarg($logFile) . ' > /dev/null';
$command = $sudo . ' ' . ($script . ' --perform 2>&1 | ' . $tee);

echo "Running Learnosity mass restoration in background process:\n{$command}\n";

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

printf("Beginning Learnosity mass restoration (pid: %d)\n", $procStatus['pid']);

// Exit: the import will continue to run in background
exit();
