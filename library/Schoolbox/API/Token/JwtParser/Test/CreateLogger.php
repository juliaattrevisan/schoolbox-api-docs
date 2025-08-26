<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser\Test;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Trait for creating a logger for testing this JWT parser, as well as for
 * creating a handler which may be queried.
 */
trait CreateLogger
{
    /**
     * Create the test handler: this object may be queried for log messages.
     *
     * @return TestHandler
     */
    protected function createHandler(): TestHandler
    {
        return new TestHandler(Level::Debug);
    }

    /**
     * Create a logger with the given handler.
     *
     * @param HandlerInterface $handler
     * @return LoggerInterface
     */
    protected function createLogger(HandlerInterface $handler): LoggerInterface
    {
        return new Logger(get_class($this), [$handler]);
    }
}
