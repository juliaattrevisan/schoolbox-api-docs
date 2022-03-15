<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Exception;

/**
 * A specific subclass of DomainExceptions spawned by the Tass API connectivity.
 * Used when you try to access a module that didn't exist ni the DSN, such as
 * the optional schoolcalendarandnotices module.
 */
class MissingModule extends \DomainException
{

}
