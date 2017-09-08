<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
