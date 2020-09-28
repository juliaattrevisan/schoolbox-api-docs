#!/usr/bin/php
<?php

/**
 * CLI Pastoral Importer
 *
 * Used when there are lots of records to import from the sis, because we have an 1800sec
 * timeout enforced by nginx that we can't override.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Pastoral\CommandLineTool\Importer;

require __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(
    new Importer(),
    [
        CliTool\Kernel::OPTION_LEVEL  => Monolog\Logger::CRITICAL,
        CliTool\Kernel::OPTION_ERRORS => E_ALL,
    ]
);
$kernel->runApp();
