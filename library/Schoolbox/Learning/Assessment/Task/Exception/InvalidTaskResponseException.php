<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Task\Exception;

/**
 * For some reason, the proposed Task Response object is invalid
 *
 * This should be thrown when we have a logic error, and get logged to Sentry so we can
 * fix it.
 */
class InvalidTaskResponseException extends \LogicException
{

}
