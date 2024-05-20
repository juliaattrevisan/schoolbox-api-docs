<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification\Cloud;

use RuntimeException;

/**
 * Exception raised when a rate limit happens when sending a notification.
 */
class RateLimitException extends RuntimeException
{
}
