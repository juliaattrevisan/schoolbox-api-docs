#!/usr/bin/php
<?php

/**
 * CLI Tool ensuring that instances of schoolbox are running the correct plan and settings for that plan
 *
 * Can be run either on its own across all instances; set-plan.php
 * or
 * To set the plan for a specific instance; 'INSTANCE_ID=xxx; set-plan.php -plan pro'
 *
 */

use Schoolbox\Application\CliTool;
use Schoolbox\Admin\Plan\Plan;

require_once __DIR__ . '/../conf/core/require.php';

$kernel = new CliTool\Kernel(new Plan(), [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
