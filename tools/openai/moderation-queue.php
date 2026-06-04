#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Admin\OpenAI\ModerationQueue\CommandLineTool;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\Kernel;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Kernel(
    new CommandLineTool(),
    [
        CliKernelAbstract::OPTION_ERRORS => E_ALL,
    ],
);
$kernel->runApp();
