#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\Kernel;
use Schoolbox\Resource\Storage\GC\CommandLineTool;

require_once __DIR__ . '/../conf/core/require.php';

$kernel = new Kernel(
    new CommandLineTool(),
    [
        CliKernelAbstract::OPTION_ERRORS => E_ALL
    ]
);
$kernel->runApp();
