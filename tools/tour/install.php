<?php

/**
 * CLI Tool for importing tour YAML files into Schoolbox DB
 *
 * Provides the import file as the first argument to the script
 *
 */

use Schoolbox\Application\CliTool;
use Schoolbox\Tour\Import;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(new Import(), [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
