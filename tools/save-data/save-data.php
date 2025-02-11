#!/usr/bin/php
<?php

/**
 * @author      Eli Lowinger
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education
 */

declare(strict_types=1);

use Monolog\Logger;
use Schoolbox\Application\CliTool\Kernel;
use Schoolbox\SaveData\Cli;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Kernel(new Cli(), [
    Kernel::OPTION_LEVEL => Logger::INFO,
]);
$kernel->runApp();
