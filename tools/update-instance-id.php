#!/usr/bin/php
<?php
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Config\InstanceIdManager;

// TODO consider whether we should manually do this to avoid a warning when instance id is not set
require_once __DIR__ . '/../conf/core/require.php';

$kernel = new CliTool\Kernel(new InstanceIdManager('abc123'));
$kernel->runApp();
