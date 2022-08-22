<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Pastoral\Type;

use InvalidArgumentException;
use Schoolbox\Filter;
use Schoolbox\User;

/**
 * Key converter for pastoral type/subtype filter.
 */
class KeyConverter extends Filter\AbstractKeyConverter
{
    /**
     * Constructor
     *
     * @param Filter\Model $filter
     * @param array        $ids
     */
    public function __construct(Filter\Model $filter, private array $ids)
    {
        parent::__construct($filter);
    }

    /**
     * @inheritdoc
     */
    protected function getItem(string $key, User $user, string $page)
    {
        return (int)$key;
    }

    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        if (!in_array($key, $this->ids, true)) {
            throw new InvalidArgumentException("No pastoral type found for key: $key");
        }
    }

    /**
     * @inheritdoc
     */
    public function possibleItemCount(): int
    {
        return count($this->ids);
    }
}
