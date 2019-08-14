#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use External\Canvas\Sync\CliImporter;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Command line application for syncing canvas-data
 */
$kernel = new CliTool\Kernel(new CliImporter());
$kernel->runApp();
