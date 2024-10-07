<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

namespace Alaress\Rules\Lang;

use RuntimeException;

/**
 * A special exception that is used for signalling that no input values matched
 * a map construct's mapping.
 *
 * If this happens, then the object's field value should not be updated.
 */
class NoMatchingMapException extends RuntimeException
{
}
