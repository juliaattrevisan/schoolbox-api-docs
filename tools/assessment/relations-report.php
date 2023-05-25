#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\Kernel;
use Schoolbox\Learning\Assessment\Relation\CommandLineTool\Reporter;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Report on any inconsistencies between the data in the assessment relation
 * cache table `assessment_relation`, and the query which populates that table.
 */

$app     = new Reporter();
$options = [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO, CliKernelAbstract::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Kernel($app, $options))->runApp();
