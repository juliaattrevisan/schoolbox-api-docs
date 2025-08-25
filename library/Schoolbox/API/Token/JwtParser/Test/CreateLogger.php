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
use Psr\Log\NullLogger;

/**
 * Trait for creating a logger for testing this JWT.
 *
 * This logger outputs all debug information when the runner is run with the
 * `OUTPUT_JWT_LOG` env var set.
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
        if (getenv('OUTPUT_JWT_LOG')) {
            return new Logger(
                get_class($this),
                [new StreamHandler(STDOUT, Level::Debug)],
            );
        }

        return new NullLogger();
    }
}
