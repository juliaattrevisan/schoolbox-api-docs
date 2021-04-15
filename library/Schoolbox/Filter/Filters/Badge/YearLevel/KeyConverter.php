<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\YearLevel;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\User;

/**
 * Converts between string concrete keys and integer (or NULL) YearLevel ids
 */
class KeyConverter extends AbstractKeyConverter
{
    /**
     * @var Collection|int[]
     */
    private $yearLevelIds;


    /**
     * @param Filter\Model     $filter
     * @param Collection|int[] $yearLevelIds
     */
    public function __construct(Filter\Model $filter, Collection $yearLevelIds)
    {
        parent::__construct($filter);
        $this->yearLevelIds = $yearLevelIds;
    }


    /**
     * @inheritdoc
     * @return int|null
     */
    protected function getItem(string $key, User $user, string $page): ?int
    {
        return Definition::KEY_NONE === $key ? null : (int)$key;
    }


    /**
     * @inheritDoc
     */
    public function possibleItemCount(): int
    {
        // +1 for KEY_NONE
        return $this->yearLevelIds->count() + 1;
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        if ($key !== Definition::KEY_NONE && !$this->yearLevelIds->keyExists($key)) {
            throw new InvalidArgumentException("No YearLevel found for key: $key");
        }
    }
}
