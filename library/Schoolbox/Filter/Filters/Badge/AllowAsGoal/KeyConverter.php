<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\AllowAsGoal;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\User;

/**
 * Converts between AllowAsGoal concrete keys and AllowAsGoal strings
 */
class KeyConverter extends AbstractKeyConverter
{

    /**
     * All 'AllowAsGoal' values known to the system (regardless of whether they are available)
     *
     * Simply the set [0, 1]
     *
     * @var Collection|string[]
     */
    private $allowAsGoal;


    /**
     * @param Filter\Model        $filter
     * @param Collection|string[] $allowAsGoal
     */
    public function __construct(Filter\Model $filter, Collection $allowAsGoal)
    {
        parent::__construct($filter);
        $this->allowAsGoal = $allowAsGoal;
    }


    /**
     * @inheritdoc
     * @return string
     */
    protected function getItem(string $key, User $user, string $page): string
    {
        $this->assertValidKey($key, $user, $page);
        return $key;
    }


    /**
     * @inheritDoc
     */
    public function possibleItemCount(): int
    {
        return $this->allowAsGoal->count();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        if (!in_array($key, $this->allowAsGoal->getAll(), true)) {
            throw new \InvalidArgumentException('AllowAsGoal Key is invalid: ' . $key);
        }
    }
}
