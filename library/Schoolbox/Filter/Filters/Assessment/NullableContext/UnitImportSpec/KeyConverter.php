<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\UnitImportSpec;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\NullableContext;

/**
 * No conversion. A int is converted to the same int (representing a unitSpec.id)
 */
class KeyConverter extends NullableContext\KeyConverter
{

    /**
     * All UnitSpecs IDs known to the system (regardless of whether they are
     * available for selection for current user)
     *
     * We use IDs instead of Models as too slow for no value
     *
     * @var Collection|int[]
     */
    private $unitSpecIds;


    /**
     * @param Filter\Model     $filter
     * @param Collection|int[] $unitSpecIds
     */
    public function __construct(Filter\Model $filter, Collection $unitSpecIds)
    {
        parent::__construct($filter);
        $this->unitSpecIds = $unitSpecIds->shallowClone();
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
        return $this->unitSpecIds[$key];
    }


    /**
     * Return all available concrete items
     *
     * @return Collection|int[]
     */
    public function getAllPossibleItems(): Collection
    {
        return $this->unitSpecIds->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, \Schoolbox\User $user, string $page): void
    {
        if (Definition::KEY_NONE === $key) {
            return;
        }
        if (!$this->unitSpecIds->offsetExists($key)) {
            throw new \InvalidArgumentException("No UnitSpec ID found for key: $key");
        }
    }
}
