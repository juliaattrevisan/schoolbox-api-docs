#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\Kernel;
use Schoolbox\Learning\Assessment\Relation\CommandLineTool\Reloader;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Reload the assessment relation materialized view `mv_assessment_relation`,
 * based on the definition of the view `v_assessment_relation`.
 */

$app     = new Reloader();
$options = [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO, CliKernelAbstract::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Kernel($app, $options))->runApp(true);
