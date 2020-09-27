#!/usr/bin/php
<?php
/**
 * Video encoder and processor
 * This script should be run regularly by cron, it picks up any videos in the queue automatically converts them
 * into playable videos
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Video\CommandLineTool\Processor;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(new Processor(), [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]);
$kernel->runApp();

