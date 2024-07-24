<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\MobileApp\CommandLineTool\CleanUpAbandoned;

require_once __DIR__ . '/../../conf/core/require.php';

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel(
    new CleanUpAbandoned(),
    [
        CliTool\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        CliTool\Kernel::OPTION_ERRORS => E_ALL,
    ]
))->runApp();
