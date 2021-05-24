<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Tass\Api\Exception;

use InvalidArgumentException;

/**
 * Thrown when Schoolbox (without having to ask TASS) is able to conclude that the configured API key is invalid
 */
class InvalidApiKey extends InvalidArgumentException
{

}
