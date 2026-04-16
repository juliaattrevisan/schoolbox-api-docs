<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util;

use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Helpers for logging things which happen in LTI controllers.
 */
class LogHelper
{
    /**
     * Helper function for logging LTI controller actions.
     *
     * @param LoggerInterface  $logger     The logger to log to.
     * @param string           $methodName The method name of the controller action.
     * @param RequestInterface $request    The incoming request: the method and path are logged.
     * @return void
     */
    public static function logControllerAction(
        LoggerInterface $logger,
        string $methodName,
        RequestInterface $request,
    ): void {
        $logger->debug(
            $methodName . ': called',
            [
                'method' => $request->getMethod(),
                'path'   => $request->getUri()->getPath(),
            ],
        );
    }

    /**
     * Helper function for logging LTI controller errors.
     *
     * @param LoggerInterface  $logger     The logger to log to.
     * @param string           $methodName The method name of the controller action.
     * @param RequestInterface $request    The incoming request: the method and path are logged.
     * @param Throwable        $exception  The error raised: its message is added to the log.
     * @return void
     */
    public static function logControllerError(
        LoggerInterface $logger,
        string $methodName,
        RequestInterface $request,
        Throwable $exception,
    ): void {
        $logger->error(
            $methodName . ': error',
            [
                'method'    => $request->getMethod(),
                'path'      => $request->getUri()->getPath(),
                'message'   => $exception->getMessage(),
                'exception' => $exception,
            ],
        );
    }
}
