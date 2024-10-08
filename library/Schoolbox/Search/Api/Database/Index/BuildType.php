<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index;

/**
 * Enum for type of index build.
 */
enum BuildType
{
    case UPDATE;
    case DELETE;
}
