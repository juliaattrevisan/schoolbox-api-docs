<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Image\Processor\Manager;
use Alaress\Image\Processor\Worker;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$opts = getopt('', ['hash:', 'cache-key:', 'transform:', 'retry-failed', 'attempts:']);
if (!isset($opts['hash'], $opts['cache-key'], $opts['transform'], $opts['attempts'])) {
    printf("The following parameters must be provided: hash, cache-key, transform, attempts\n");
    exit(1);
}
$worker = new Worker(
    $opts['hash'],
    $opts['cache-key'],
    unserialize(base64_decode($opts['transform'])),
    Manager::TIMED_OUT_THRESHOLD,
    Manager::IMAGICK_TIME_LIMIT,
    (bool)($opts['retry-failed'] ?? false),
    (int)$opts['attempts'],
);
(new CliTool\Kernel($worker, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
