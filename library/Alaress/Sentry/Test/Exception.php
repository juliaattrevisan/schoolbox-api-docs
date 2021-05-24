<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Test;

use Exception as BaseException;

/**
 * Test exception for testing whether the exception filter matches on interface.
 */
class Exception extends BaseException implements IException
{

}
