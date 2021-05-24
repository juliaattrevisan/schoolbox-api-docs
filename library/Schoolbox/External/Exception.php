<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External;

/**
 * Thrown when:
 * * an external connector method is unable to connect at all, or
 * * an external connector method cannot return results, and returning an empty value is not appropriate
 */
class Exception extends \Exception
{

}
