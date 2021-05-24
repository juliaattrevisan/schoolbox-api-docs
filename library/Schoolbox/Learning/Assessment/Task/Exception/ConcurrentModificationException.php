<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Task\Exception;

/**
 * Thrown when a user attempts to change a task's completion status to the status it already has.
 *
 * Most likely caused when we have multiple users interacting with the same task at the same time.
 *
 * No need to log this to sentry.
 */
class ConcurrentModificationException extends \LogicException
{

}
