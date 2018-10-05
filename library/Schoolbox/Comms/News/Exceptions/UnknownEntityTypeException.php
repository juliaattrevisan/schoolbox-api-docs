<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a user tries to build a strategy for an unrecognised entityType
 */
class UnknownEntityTypeException extends InvalidArgumentException
{

}
