#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\CommandLineTool\RepairRowFormat;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

// Note this script needs to bypass the maintenance mode check because it is run from upgrade-instances.sh.
$kernel = new CliTool\Kernel(new RepairRowFormat(), [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO], true);
$kernel->runApp();
