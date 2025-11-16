#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

use Alaress\Scheduler\Trigger\CommandLineTool;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\Kernel;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Kernel(
    new CommandLineTool(),
    [
        CliKernelAbstract::OPTION_ERRORS => E_ALL,
        CliKernelAbstract::OPTION_LEVEL  => Monolog\Logger::INFO
    ],
);
$kernel->runApp();
