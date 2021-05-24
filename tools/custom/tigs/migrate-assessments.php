#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Custom\Tigs\Summative\CliAssessmentMigrator;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::NOTICE, CliTool\Kernel::OPTION_ERRORS => E_ALL,];
(new CliTool\Kernel(new CliAssessmentMigrator(), $options))->runApp();
