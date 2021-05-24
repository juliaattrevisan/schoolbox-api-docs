<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Mapper\Exception;

/**
 * Thrown when you attempt to work with a model's property, but that property has
 * not been set due a lack of hydration.
 *
 * eg, if a model has an enum property with values [X,Y,Z], and helper methods isX(), isY(), isZ() then
 *     each helper method would throw an instance of this exception if the model's property is NULL.
 *
 * This represents a logic bug and should be logged to sentry.
 */
class InsufficientHydrationException extends \LogicException
{

}
