#!/usr/bin/php
<?php
/**
 * Lock inspector.
 *
 * Inspects the status of all locks within Schoolbox.
 *
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Alaress\Lock\CommandLineTool\Inspector;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(
    new Inspector(),
    [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]
);
$kernel->runApp();
