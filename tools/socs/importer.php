#!/usr/bin/env php
<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new \Schoolbox\Socs\Cli\ImporterTool();

$options = [
    CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO,
    CliTool\Kernel::OPTION_ERRORS => E_ALL,
];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
