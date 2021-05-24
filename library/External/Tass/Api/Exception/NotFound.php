<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Exception;

use External\Tass\Api\ITassApiException;
use UnexpectedValueException;

/**
 * Thrown when you request an entity from the API, but none found in Tass
 *
 * Not thrown if you request ALL and none found - that just gives you an empty set.
 */
class NotFound extends UnexpectedValueException implements ITassApiException
{

}
