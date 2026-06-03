#!/usr/bin/env php
<?php

/**
 * Submit pending IB candidates to IBIS.
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Admin\InternationalBaccalaureate\Cli\RegisterCandidatesTool;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new RegisterCandidatesTool();
$options = [
    CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO,
    CliKernelAbstract::OPTION_ERRORS => E_ALL,
];

(new CliTool\Kernel($app, $options))->runApp();
