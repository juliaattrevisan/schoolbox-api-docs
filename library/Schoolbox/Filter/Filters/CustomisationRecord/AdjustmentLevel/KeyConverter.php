<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord\AdjustmentLevel;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\User;

/**
 * Converts between string concrete keys and integer (or NULL) AdjustmentLevel ids
 */
class KeyConverter extends AbstractKeyConverter
{
    public const int NONE_KEY_VALUE = -1;
    /**
     * @var Collection|int[]
     */
    private $adjustmentLevelIds;


    /**
     * @param Filter\Model     $filter
     * @param Collection|int[] $adjustmentLevelIds
     */
    public function __construct(Filter\Model $filter, Collection $adjustmentLevelIds)
    {
        parent::__construct($filter);
        $this->adjustmentLevelIds = $adjustmentLevelIds;
    }


    /**
     * @inheritdoc
     * @return int|null
     */
    protected function getItem(string $key, User $user, string $page): ?int
    {
        return Definition::KEY_NONE === $key ? self::NONE_KEY_VALUE : (int)$key;
    }


    /**
     * @inheritDoc
     */
    public function possibleItemCount(): int
    {
        // +1 for KEY_NONE
        return $this->adjustmentLevelIds->count() + 1;
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        if ($key !== Definition::KEY_NONE && !$this->adjustmentLevelIds->keyExists($key)) {
            throw new InvalidArgumentException("No AdjustmentLevel found for key: $key");
        }
    }
}
