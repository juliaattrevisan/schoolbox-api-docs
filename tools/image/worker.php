<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Image\Processor\Worker;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

(new CliTool\Kernel(new Worker(), [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
