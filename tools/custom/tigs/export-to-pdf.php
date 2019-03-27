#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Custom\Tigs\Summative\CliPdfGenerator;

require_once __DIR__ . '/../../../conf/core/require.php';

$kernel = new CliTool\Kernel(new CliPdfGenerator(), [CliTool\Kernel::OPTION_RUN_AS_ROOT => false]);
$kernel->runApp();
