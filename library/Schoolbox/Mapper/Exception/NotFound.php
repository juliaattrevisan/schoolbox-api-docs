<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper\Exception;

use Schoolbox\Mapper\Exception as BaseException;

/**
 * Exception that is thrown when a user requests an object which doesn't exist.
 */
class NotFound extends BaseException
{
    protected $message = 'Unable to find object';
}
