<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser\Exception;

use RuntimeException;

/**
 * Exception raised when a JWT provided to Schoolbox is not parseable.
 *
 * This implies that the JWT itself is malformed, and thus it's pointless to
 * attempt to verify whether it is acceptable.
 */
class Unparseable extends RuntimeException implements Base
{
}
