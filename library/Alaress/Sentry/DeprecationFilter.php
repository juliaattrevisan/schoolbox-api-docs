<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry;

use ErrorException;
use Schoolbox\Deprecation\Registry;
use Sentry\Event;

/**
 * Filter for errors raised as part of a deprecation warning.
 *
 * This uses the rules set out in `Schoolbox\Deprecation\Registry`: if the
 * deprecation was raised in vendor code, the event is not reported.
 */
class DeprecationFilter implements IFilter
{
    /**
     * @inheritdoc
     */
    public function __invoke(Event $event): ?Event
    {
        // Unfortunately: Sentry's event object contains no obvious values for
        // the exception's content: only its class and stack trace.
        foreach ($event->getExceptions() as $exception) {
            // The one thing I do know is `\Sentry\ErrorHandler::handleError`
            // raises an `ErrorException` upon receiving an unhandled error.
            if (is_a($exception->getType(), ErrorException::class, true)) {
                // I don't know the error's level, which could be matched
                // against `E_DEPRECATED` or `E_USER_DEPRECATED`.
                // I can only make a guess against the exception's value, which
                // will be prefixed with a string from
                // `\Sentry\ErrorHandler::ERROR_LEVELS_DESCRIPTION`.
                $value = strtolower($exception->getValue());
                if (str_starts_with($value, 'deprecated:') || str_starts_with($value, 'user deprecated:')) {
                    // Find the last frame of the stack trace, and compare its
                    // path against the registry.
                    $stackTrace = $exception->getStacktrace();
                    if ($stackTrace) {
                        // There is no easy way to get the last frame, and
                        // `getFrames` isn't guaranteed to return an array, so
                        // need to iterate the whole thing.
                        $lastFrame = null;
                        foreach ($stackTrace->getFrames() as $frame) {
                            $lastFrame = $frame;
                        }

                        // Finally: can skip the event if the last frame's path
                        // is not silenced.
                        if ($lastFrame && Registry::silenceWarningsForPath($lastFrame->getAbsoluteFilePath())) {
                            return null;
                        }
                    }
                }
            }
        }
        return $event;
    }
}
