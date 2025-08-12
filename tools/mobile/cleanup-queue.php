#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

/**
 * Clean up the mobile daemon's accumulated message queue.
 *
 * Until now, the mobile daemon's queue's messages were stored without a TTL
 * (therefore, indefinitely), even though they have no use once they have been
 * processed by the daemon.
 *
 * This script will find these leftover messages, and set a TTL on them.
 */

use Schoolbox\Application\CliTool\Kernel;
use Schoolbox\Realtime\Mobile\CommandLineTool\CleanupQueue;

require __DIR__ . '/../../conf/core/require.php';

$kernel = new Kernel(new CleanupQueue(), [], true);
$kernel->runApp();
