<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database;

use DateTimeInterface;

/**
 * Data object for a raw match from a database.
 */
class RawMatch
{
    public function __construct(
        public int $id,
        public string $title,
        public string $type,
        public ?DateTimeInterface $date,
        public float $relevance,
    ) {
    }
}
