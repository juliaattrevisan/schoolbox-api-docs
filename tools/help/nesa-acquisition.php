#!/usr/bin/php
<?php
/**
 * NESA Data Acquisition Crontab
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Mapper\Exception as MapperException;

$fields  = ['help', 'folder:'];
$options = require_once __DIR__ . '/../../conf/core/require-cli.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'NESA Data Acquisition',
    [
        'folder' => isset($options['folder']) ? $options['folder'] : null,
    ]
);

if (isset($options['help'])) {
    echo <<<HELP
Investigates implementations of the Teacher Course to find out which rubrics were received by users.

Usage: nesa-acquisition.php [--max-attempts]
    --folder:   A specific folder to investigate, otherwise processes all folders using Teacher Course


HELP;
    exit;
}

$kernel->run(function (Di\Container $di, LoopInterface $loop, Logger $log, array $options) {
    $folderId = $options['folder'];

    /* If a folder was specified, accumulate the rubric data for this folder only
     * Otherwise process everyone
     */
    if ($folderId) {
        $log->critical("Acquiring {$folderId}");
    }
    else {
        $log->critical("Acquiring all folders");
    }

    exit(0);
});
