<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser\Exception;

use Throwable;

/**
 * Base interface for all exceptions raised when parsing a JWT provided to
 * Schoolbox.
 */
interface Base extends Throwable
{
}
