#!/usr/bin/env php
<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

use Alaress\Redis\CommandLineTool\Import;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

(new CliTool\Kernel(new Import(), [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]))->runApp();
