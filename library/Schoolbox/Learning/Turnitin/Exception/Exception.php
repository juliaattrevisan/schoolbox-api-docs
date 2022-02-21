<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Exception;

/**
 * A generic superclass for all Turnitin-based exceptions
 * so you don't have to continually list them accumulatively
 * in catch() statements, leading to scenarios where they're
 * not caught at all, but should have been
 */
class Exception extends \DomainException
{

}
