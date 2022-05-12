<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Module\Cache;

use RuntimeException;

/**
 * Exception class for any errors which might happen whilst fetching the route
 * or module tables from cache.
 */
class FetchException extends RuntimeException
{
}
