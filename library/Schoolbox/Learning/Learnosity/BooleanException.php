<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

/**
 * Sometiems learnosity sdk gives you a random boolean;
 * suspected due to curl_exec failing
 */
class BooleanException extends \UnexpectedValueException
{
}
