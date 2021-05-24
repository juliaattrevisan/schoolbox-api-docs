#!/usr/bin/php
<?php

/**
 * Invokes the RecursiveUndeleter CLI App
 *
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Mapper\RecursiveUndeleterCli;

require_once __DIR__ . '/../conf/core/require.php';

$kernel = new CliTool\Kernel(new RecursiveUndeleterCli(), [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
