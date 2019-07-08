<?php
/**
 * AccessDenied
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Application\Exception;


class AccessDenied extends Exception
{
    /**
     * @inheritdoc
     */
    protected $httpStatusCode = 401;
}
