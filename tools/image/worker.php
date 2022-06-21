<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Image\Processor\Manager;
use Alaress\Image\Processor\Worker;
use Schoolbox\Application\CliTool;

[$hash, $cacheKey, $transform, $retryFailed, $attemptsPerTransform] = $argv;
if (!isset($hash, $cacheKey, $transform, $retryFailed, $attemptsPerTransform)) {
    printf("At least 5 parameters must be passed: received %d\n", $argc);
    exit(1);
}
$worker = new Worker(
    $hash,
    $cacheKey,
    $transform,
    Manager::TIMED_OUT_THRESHOLD,
    Manager::IMAGICK_TIME_LIMIT,
    (bool)$retryFailed,
    (int)$attemptsPerTransform,
);
(new CliTool\Kernel($worker, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
