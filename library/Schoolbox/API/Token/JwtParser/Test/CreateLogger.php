<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser\Test;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Trait for creating a logger for testing this JWT.
 *
 * This logger should output all debug information when run in a test
 * environment, but not when run in CI.
 */
trait CreateLogger
{
    /**
     * Create the logger.
     *
     * @return LoggerInterface
     */
    protected function createLogger(): LoggerInterface
    {
        return new Logger(
            get_class($this),
            [new StreamHandler(STDOUT, Level::Debug),],
        );
    }
}
