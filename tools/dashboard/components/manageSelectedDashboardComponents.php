#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Component\CliTool\DisableExpensiveComponents;

require_once __DIR__ . '/../../../conf/core/require.php';

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel(
    new DisableExpensiveComponents(),
    [CliKernelAbstract::OPTION_ERRORS => E_ALL,]
))->runApp();
