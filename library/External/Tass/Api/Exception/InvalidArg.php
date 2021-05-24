<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Exception;

use External\Tass\Api\ITassApiException;
use InvalidArgumentException;

/**
 * Thrown when:
 * * A required arg is not supplied, or
 * * An arg is invalid (eg 'area-code must be an integer')
 *
 * This is not related to invalid credentials, for that:
 * @see Unauthorised
 */
class InvalidArg extends InvalidArgumentException implements ITassApiException
{

}
