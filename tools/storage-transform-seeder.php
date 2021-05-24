#!/usr/bin/php
<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Storage\Transform\Seeder;

require_once __DIR__ . '/../conf/core/require.php';

$kernel = new CliTool\Kernel(new Seeder(), [
    CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO,
]);
$kernel->runApp();
