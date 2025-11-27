#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

use Alaress\Scheduler\Trigger\CommandLineTool\Scalpel;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\Kernel;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Kernel(
    new Scalpel(),
    [
        CliKernelAbstract::OPTION_ERRORS => E_ALL,
        CliKernelAbstract::OPTION_LEVEL  => Monolog\Logger::INFO
    ],
);
$kernel->runApp();
