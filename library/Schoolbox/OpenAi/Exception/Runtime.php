<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Exception;

use RuntimeException;

/**
 * Parent class for various runtime exceptions thrown by AI clients and agents.
 */
abstract class Runtime extends RuntimeException
{
}
