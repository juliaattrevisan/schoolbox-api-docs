#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Learning\Learnosity\Cli\MaxZeroAttemptsTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app     = new MaxZeroAttemptsTool();
$options = [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO, CliKernelAbstract::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp(true);
