<?php

/**
 * Exception for mapper related exceptions.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper;

class Exception extends \Exception
{
    protected $message = 'Instance of object not found';
}
