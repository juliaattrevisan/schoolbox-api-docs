<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image\Exception;

use InvalidArgumentException;

/**
 * Thrown if we attempt to transform an image that has layers but is not a GIF format.
 */
class LayeredNonGifException extends InvalidArgumentException
{

}
