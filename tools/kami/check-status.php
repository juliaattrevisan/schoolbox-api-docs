#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\InlineGrader\Kami;

require __DIR__ . '/../../conf/core/require.php';

/**
 * Checks whether documents that are still 'processing' in Kami have finished processing
 * If so, updates the document's status in Schoolbox
 */

$kernel = new CliTool\Kernel(
    new Kami\CommandLineTool\StatusChecker(),
    [CliTool\Kernel::OPTION_ERRORS => E_ALL]
);
$kernel->runApp();
