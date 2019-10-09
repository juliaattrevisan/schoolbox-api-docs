#!/usr/bin/php
<?php
/**
 *
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Custom\Tps\AssessmentFeedbackImporter;

require_once __DIR__ . '/../../../conf/core/require.php';

$kernel = new CliTool\Kernel(new AssessmentFeedbackImporter(),
    [
        CliTool\Kernel::OPTION_LEVEL  => Monolog\Logger::INFO,
        CliTool\Kernel::OPTION_ERRORS => E_ALL,
    ]);
$kernel->runApp();
