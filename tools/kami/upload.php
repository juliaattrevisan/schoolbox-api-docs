#!/usr/bin/php
<?php

/**
 * Uploads eligible documents from Schoolbox into Kami
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\InlineGrader\Kami\CommandLineTool\Uploader;

require __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(new Uploader(), [CliTool\Kernel::OPTION_ERRORS => E_ALL,]);
$kernel->runApp();
