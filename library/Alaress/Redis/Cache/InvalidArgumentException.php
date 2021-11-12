<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Redis\Cache;

use InvalidArgumentException as BaseInvalidArgumentException;
use Psr\Cache\InvalidArgumentException as PsrCacheInvalidArgumentException;

/**
 * Invalid argument exception for the Redis cache.
 */
class InvalidArgumentException extends BaseInvalidArgumentException implements PsrCacheInvalidArgumentException
{
}
