<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
