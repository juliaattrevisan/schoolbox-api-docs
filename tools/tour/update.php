<?php

/**
 * CLI Tool for updating tour YAML files into Schoolbox DB
 *
 * Provides the import file as the first argument to the script
 *
 * WARNING: Not complete, can only update text in steps
 */

use Schoolbox\Application\CliTool;
use Schoolbox\Tour\Update;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(new Update(), [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
