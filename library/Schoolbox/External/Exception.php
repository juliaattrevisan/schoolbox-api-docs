<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
