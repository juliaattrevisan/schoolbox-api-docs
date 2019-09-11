<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Mapper\Exception;

use UnexpectedValueException;

/**
 * Thrown by mapper callbacks (especially pre-save) if the callback fails
 * and we need to pass information about the failure back up the call stack.
 *
 * @see \Schoolbox\Mapper::save()
 */
class CallbackException extends UnexpectedValueException
{

}
