<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Exception;

/**
 * A connection Turn-it-in based exception, when Turn-it-in
 * had died because of the timed out.
 */
class ConnectException extends \DomainException
{

}
