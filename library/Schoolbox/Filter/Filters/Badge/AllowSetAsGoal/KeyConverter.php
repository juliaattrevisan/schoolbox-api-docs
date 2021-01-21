<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\AllowSetAsGoal;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\User;

/**
 * Converts between AllowSetAsGoal concrete keys and AllowSetAsGoal strings
 */
class KeyConverter extends AbstractKeyConverter
{

    /**
     * All 'AllowSetAsGoal' values known to the system (regardless of whether they are available)
     *
     * Simply the set [0, 1]
     *
     * @var Collection|string[]
     */
    private $allowSetAsGoal;


    /**
     * @param Filter\Model        $filter
     * @param Collection|string[] $allowSetAsGoal
     */
    public function __construct(Filter\Model $filter, Collection $allowSetAsGoal)
    {
        parent::__construct($filter);
        $this->allowSetAsGoal = $allowSetAsGoal;
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
        return $this->allowSetAsGoal->count();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        if (!in_array($key, $this->allowSetAsGoal->getAll(), true)) {
            throw new \InvalidArgumentException('AllowSetAsGoal Key is invalid: ' . $key);
        }
    }
}
