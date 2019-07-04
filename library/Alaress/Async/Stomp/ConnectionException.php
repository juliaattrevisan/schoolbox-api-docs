<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp;

use RuntimeException;

/**
 * Exception raised when a connection to a STOMP service fails.
 */
class ConnectionException extends RuntimeException
{
}
