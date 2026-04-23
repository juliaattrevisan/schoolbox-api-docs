<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\ToolsDaemon;

use RuntimeException;

/**
 * Parent class for exceptions which may be raised by the tools daemon client.
 */
class ClientException extends RuntimeException
{
}
