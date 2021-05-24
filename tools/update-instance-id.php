#!/usr/bin/php
<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Config\InstanceIdManager;

// Special case for this tool, we want to be able to repair an invalid instance ID
define('PERMIT_INVALID_INSTANCE_ID', true);

require_once __DIR__ . '/../conf/core/require.php';

$kernel = new CliTool\Kernel(new InstanceIdManager());
$kernel->runApp();
