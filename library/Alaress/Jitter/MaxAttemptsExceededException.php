<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Jitter;

use RuntimeException;

/**
 * Exception that is thrown when `Jitter::calculateJitter` has attempted too
 * many times.
 */
class MaxAttemptsExceededException extends RuntimeException
{
}
