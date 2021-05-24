#!/usr/bin/php
<?php

/**
 * Merge duplicate rubric indicators.
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Rubric\Repair;

require __DIR__ . '/../../conf/core/require.php';

$options = [
    CliTool\Kernel::OPTION_ERRORS => E_ALL,
];

try {
    (new CliTool\Kernel(new Repair\Folder(), $options))->runApp();
} catch (Throwable $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
