<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Exception;

/**
 * Exception raised when the requested feature is implemented, but it is not
 * supported by the requested provider.
 */
class FeatureNotSupportedByProvider extends Runtime
{
}
