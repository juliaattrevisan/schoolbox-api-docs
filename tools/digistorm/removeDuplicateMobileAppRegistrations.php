#!/usr/bin/php
<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\MobileApp\CommandLineTool\RemoveDuplicates;

require_once __DIR__ . '/../../conf/core/require.php';

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel(
    new RemoveDuplicates(),
    [
        CliTool\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        CliTool\Kernel::OPTION_ERRORS => E_ALL,
    ]
))->runApp();
