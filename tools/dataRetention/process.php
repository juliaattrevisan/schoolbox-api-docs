#!/usr/bin/env php
<?php

/**
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\Kernel;
use Schoolbox\Admin\DataRetention\CommandLineTool as DataRetentionCli;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Kernel(
    new DataRetentionCli(),
    [
        CliKernelAbstract::OPTION_ERRORS => E_ALL
    ]
);
$kernel->runApp();
