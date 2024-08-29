<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Exception;

/**
 * A Turn-it-in connection based exception, when Turn-it-in
 * had died and the connection timed out because it couldn't
 * reach the service
 */
class ConnectException extends \DomainException
{
}
