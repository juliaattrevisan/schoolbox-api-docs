#!/usr/bin/php
<?php

/**
 * Deletes unused indicators for a specific folder
 *
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Rubric\Repair\Unused\Delete\Indicator;

require __DIR__ . '/../../conf/core/require.php';

$options = [
    CliTool\Kernel::OPTION_ERRORS => E_ALL,
];
try {
    (new Schoolbox\Application\CliTool\Kernel(new Indicator(), $options))->runApp();
} catch (Throwable $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
