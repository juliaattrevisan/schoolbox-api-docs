#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Aura\Di\ContainerBuilder;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Application\CliTool\Kernel;

require_once __DIR__ . '/../../../conf/core/require.php';

// This creates a DI container without creating the kernel:
// this script doesn't actually need to create a kernel, as it just backgrounds
// an actual call.
$di = Kernel::installDependencies((new ContainerBuilder())->newInstance());

$scriptName = realpath(__DIR__ . '/../../../tools/mobile/cleanup-queue.php');
$script     = 'php ' . escapeshellarg($scriptName) . ' -p';
$version    = new Schoolbox\Version\Version($di->get('config'));
if ($version->isCloudHosted()) {
    echo <<<OUTPUT
    Not running mobile cleanup queue script as background process on cloud instances.

    The below command needs to be run manually on cloud, and only once per cluster:
    $script --multi-instance

    OUTPUT;
    exit;
}

$sudo    = 'sudo -u ' . escapeshellarg(App::WEB_PROCESS_USER);
$logFile = '/var/log/schoolbox/' . INSTANCE_ID . '/mobile-cleanup-queue.log';

// Can't pipe whilst retaining sudo user AFAIK:
// need to tee and send output to null
$tee     = $sudo . ' tee -a ' . escapeshellarg($logFile) . ' > /dev/null';
$command = $sudo . ' ' . ($script . ' --perform 2>&1 | ' . $tee);

// Run the whole thing in background
echo "Running mobile cleanup queue script as background process:\n$command\n";
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
printf("Beginning mobile cleanup queue script (pid: %d)\n", $procStatus['pid']);

// Exit: the process will continue to run in background.
exit;
