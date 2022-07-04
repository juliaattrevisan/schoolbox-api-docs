<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser\Exception;

use RuntimeException;

/**
 * Exception raised when a JWT provided to Schoolbox is rejected before
 * evaluating the user which it identifies.
 *
 * This implies that the JWT is syntactically valid, but does not pass the JWT
 * verification mechanisms. That could be one of the following reasons:
 * * it may be expired
 * * its accept or issued at time may be in the future
 * * its signature may be invalid
 * * the key used to attempt to verify the JWT is invalid
 */
class Rejected extends RuntimeException
{
}
