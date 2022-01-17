#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Redis\CommandLineTool\Report;
use Monolog\Logger;
use Schoolbox\Application\CliTool;

require __DIR__ . '/../../conf/core/require.php';

(new CliTool\Kernel(new Report(), [CliTool\Kernel::OPTION_LEVEL => Logger::NOTICE]))->runApp();
