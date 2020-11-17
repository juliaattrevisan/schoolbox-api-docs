#!/usr/bin/php
<?php
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Storage;

require_once __DIR__ . '/../../conf/core/require.php';

// Ensure user can preview dry-run results first
$kernelOptions = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG];
$kernel        = new CliTool\Kernel(new Storage\DeleteTransforms(), $kernelOptions);
$kernel->runApp();
