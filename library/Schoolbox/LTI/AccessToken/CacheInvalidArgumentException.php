<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\AccessToken;

use InvalidArgumentException;
use Psr\Cache\InvalidArgumentException as PsrCacheInvalidArgumentException;

class CacheInvalidArgumentException extends InvalidArgumentException implements PsrCacheInvalidArgumentException
{
}
