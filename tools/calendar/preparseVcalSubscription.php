#!/usr/bin/php
<?php

/**
 * Pre-parse vCal calendar subscriptions.
 *
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Calendar\CommandLineTool\PreparseVcal;

require __DIR__ . '/../../conf/core/require.php';

// ROUTING APP
$kernel = new CliTool\Kernel(
    new PreparseVcal(),
    [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]
);

$kernel->runApp();
