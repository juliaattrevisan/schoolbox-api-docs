<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\UnitImpl;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\NullableContext;

/**
 * No conversion. A int is converted to the same int (representing a unitSpec.id)
 */
class KeyConverter extends NullableContext\KeyConverter
{

    /**
     * All UnitImpls IDs known to the system (regardless of whether they are
     * available for selection for current user)
     *
     * We use IDs instead of Models as models are too slow to build and add no extra value
     *
     * @var Collection|int[]
     */
    private $unitImplIds;


    /**
     * @param Filter\Model     $filter
     * @param Collection|int[] $unitImplIds
     */
    public function __construct(Filter\Model $filter, Collection $unitImplIds)
    {
        parent::__construct($filter);
        $this->unitImplIds = $unitImplIds->shallowClone();
    }


    /**
     * Given a concrete key (such as returned from a user's filter panel selections), return the corresponding item
     *
     * @inheritdoc
     * @return null|int
     */
    public function getItem(string $key, \Schoolbox\User $user, string $page): ?int
    {
        if (Definition::KEY_NONE === $key) {
            return null;
        }
        $this->assertValidKey($key, $user, $page);
        return $this->unitImplIds[$key];
    }


    /**
     * Return all available concrete items
     *
     * @return Collection|int[]
     */
    public function getAllPossibleItems(): Collection
    {
        return $this->unitImplIds->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, \Schoolbox\User $user, string $page): void
    {
        if (Definition::KEY_NONE === $key) {
            return;
        }
        if (!$this->unitImplIds->offsetExists($key)) {
            throw new \InvalidArgumentException("No UnitImpl ID found for key: $key");
        }
    }
}
