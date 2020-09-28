#!/usr/bin/php
<?php

/**
 * Finds all docs with the a status of 'error' and optional 'error_message' and puts them back into the queue for re-uploading.
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\InlineGrader\Kami;

require __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(
    new Kami\CommandLineTool\ErrorRequeuer(),
    [CliTool\Kernel::OPTION_ERRORS => E_ALL]
);
$kernel->runApp();
