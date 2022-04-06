#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Alaress\Csv\Cli;

require_once __DIR__ . '/../conf/core/require.php';

$kernel = new CliTool\Kernel(new Cli(), [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
