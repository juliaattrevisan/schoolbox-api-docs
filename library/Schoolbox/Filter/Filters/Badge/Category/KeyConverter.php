<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\Category;

use InvalidArgumentException;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\User;

/**
 * Key converter for the badge category filter.
 */
class KeyConverter extends AbstractKeyConverter
{
    /**
     * Extant category IDs
     */
    private array $categoryIds;


    /**
     * Constructor
     *
     * @param Filter\Model $filter
     * @param array        $categoryIds
     */
    public function __construct(Filter\Model $filter, array $categoryIds)
    {
        parent::__construct($filter);
        $this->categoryIds = $categoryIds;
    }


    /**
     * @inheritdoc
     */
    protected function getItem(string $key, User $user, string $page): int
    {
        $this->assertValidKey($key, $user, $page);
        return (int)$key;
    }


    /**
     * @inheritDoc
     */
    public function possibleItemCount(): int
    {
        return count($this->categoryIds);
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        if (!in_array($key, $this->categoryIds, false)) {
            throw new InvalidArgumentException('Badge category is invalid: ' . $key);
        }
    }
}
