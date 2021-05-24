#!/usr/bin/php
<?php

/**
 * Delete unused duplicate rubric capabilities.
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Rubric\Repair\Duplicate\Delete\Capability;

require __DIR__ . '/../../conf/core/require.php';

$options = [
    CliTool\Kernel::OPTION_ERRORS => E_ALL,
];

try {
    (new Schoolbox\Application\CliTool\Kernel(new Capability(), $options))->runApp();
} catch (Throwable $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
