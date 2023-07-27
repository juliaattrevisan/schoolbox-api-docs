<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api;

use DateTimeInterface;
use Schoolbox\User;

/**
 * Data object for a set of search filters.
 */
class Filter
{
    /**
     * Constructor
     *
     * @param array<string>|null     $types    if defined, return only results of this type.
     * @param DateTimeInterface|null $fromDate if defined, return only results created after this date.
     * @param DateTimeInterface|null $toDate   if defined, return only results created before this date.
     * @param User|null              $creator  if defined, return only results created by this user.
     */
    public function __construct(
        public ?array $types,
        public ?DateTimeInterface $fromDate,
        public ?DateTimeInterface $toDate,
        public ?User $creator,
    ) {
    }
}
