<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser\Test;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
        // To view debug logs in JWT parsers, uncomment the below:
//        return new \Monolog\Logger(
//            get_class($this),
//            [new \Monolog\Handler\StreamHandler(STDOUT, \Monolog\Level::Debug),],
//        );

        return new NullLogger();
    }
}
