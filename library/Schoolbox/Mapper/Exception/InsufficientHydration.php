<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Mapper\Exception;

/**
 * Thrown when you attempt to work with a model's property, but that property has
 * not been set due a lack of hydration.
 *
 * This represents a logic bug and should be logged to sentry.
 */
class InsufficientHydration extends \UnexpectedValueException
{

}
