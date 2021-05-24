<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
