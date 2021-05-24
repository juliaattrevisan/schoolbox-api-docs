<?php

/**
 * AccessDenied
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\Exception;

class AccessDenied extends Exception
{
    /**
     * @inheritdoc
     */
    protected $httpStatusCode = 401;
}
