<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser\Exception;

use RuntimeException;

/**
 * Exception raised when a JWT provided to Schoolbox is accepted, but the JWT
 * either:
 * * has no usable identity claim
 * * makes an identity claim which does not match a Schoolbox user
 */
class InvalidIdentity extends RuntimeException
{
}
