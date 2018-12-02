<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\Exceptions;

/**
 * Thrown if we attempt to render a config with a renderer where the config and renderer have
 * different field types. This is a developer error that should be fixed in the code.
 */
class FieldTypeMismatchException extends \InvalidArgumentException
{

}
