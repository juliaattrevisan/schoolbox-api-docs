<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

use Schoolbox\Search\Api\Cursor;
use Schoolbox\Search\Api\Filter;

/**
 * A hydrator for search result matches.
 *
 * Responsible for converting a raw match into an actual result.
 */
interface IHydrator
{
    public function accepts(): bool;

    public function hydrate($match): array;
}
