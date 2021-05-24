<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Application;

/**
 * Thrown when a Kernel is attempted to be loaded in an incompatible environment
 */
class WrongSapiException extends \UnexpectedValueException
{

}
