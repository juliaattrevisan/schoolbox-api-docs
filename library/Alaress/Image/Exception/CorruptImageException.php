<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image\Exception;

use UnexpectedValueException;

/**
 * Thrown if image appears to be corrupt.
 *
 * eg if unable to parse width/height
 */
class CorruptImageException extends UnexpectedValueException
{

}
