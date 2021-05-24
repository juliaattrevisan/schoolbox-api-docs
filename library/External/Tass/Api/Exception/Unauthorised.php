<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Exception;

use External\Tass\Api\ITassApiException;
use RuntimeException;

/**
 * Thrown when the Tass API responds with Unauthorised
 *
 * This means the credentials used to connect to the API are incorrect, as opposed to a method arg being invalid
 * @see InvalidArg
 */
class Unauthorised extends RuntimeException implements ITassApiException
{

}
