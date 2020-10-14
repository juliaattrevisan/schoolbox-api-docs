<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Transformation;

use Alaress\Image\ITransform;
use OutOfBoundsException;

/**
 * Encapsulates a set of available image manipulations.
 */
interface ITransformation
{
    /**
     * Returns a a named set of transforms
     *
     * @param string $key
     * @throws OutOfBoundsException if transformation isn't found
     * @return ITransform[]
     */
    public function retrieve(string $key): array;
}
