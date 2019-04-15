#!/usr/bin/php
<?php
/**
 * Merge duplicate rubric indicators.
 *
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Rubric\Repair\Duplicate\Merge\Indicator;

$opts = require __DIR__ . '/../../conf/core/require-cli.php';

// TODO - START - Delete this once SA-8248 is merged. It will be handled by a single core require.php
require_once __DIR__ . '/../../www/conf/conf.php';
require_once __DIR__ . '/../../www/include/stdfuncs.php';
require_once __DIR__ . '/../../library/constants.php';
set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../../library/Autoloader.php';
Autoloader::initialise();
// TODO - END

$options = [
    CliTool\Kernel::OPTION_ERRORS => E_ALL,
];

try {
    (new Schoolbox\Application\CliTool\Kernel(new Indicator(), null, $options))->runApp();
} catch (Throwable $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
