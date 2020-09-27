#!/usr/bin/php
<?php

/**
 * Synergetic Pastoral Exporter
 *
 * This script manually syncs all pastoral care records within Schoolbox to
 * the external pastoral care system, if one is configured.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Pastoral\CommandLineTool\Exporter;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(new Exporter());
$kernel->runApp();
