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
 * Thrown if:
 * * unable to json decode the API call's response body, or
 * * doesn't decode to an array. (ensures we don't get type-errors on methods that expect an array)
 */
class ResponseDecodeException extends RuntimeException implements ITassApiException
{

}
