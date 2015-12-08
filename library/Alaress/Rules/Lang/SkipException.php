<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Rules\Lang;

use RuntimeException;

/**
 * A special exception that is used for signalling that application of all
 * subsequent rules is to be skipped.
 *
 * @package Alaress\Rules\Lang
 */
class SkipException extends RuntimeException
{
}
