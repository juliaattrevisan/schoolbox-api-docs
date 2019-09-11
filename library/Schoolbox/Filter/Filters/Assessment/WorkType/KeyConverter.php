<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\WorkType;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\User;

/**
 * Converts between concrete keys (workType.ids or KEY_NONE) and workType.id or NULL
 */
class KeyConverter extends AbstractKeyConverter
{

    /**
     * All WorkType IDs known to the system (regardless of whether they are available)
     *
     * @var Collection|int[]
     */
    private $workTypeIds;


    /**
     * @param Filter\Model     $filter
     * @param Collection|int[] $workTypeIds
     */
    public function __construct(Filter\Model $filter, Collection $workTypeIds)
    {
        parent::__construct($filter);
        $this->workTypeIds = $workTypeIds->shallowClone();
    }


    /**
     * @inheritdoc
     */
    public function getItem(string $key, User $user, string $page): ?int
    {
        if(Definition::KEY_NONE === $key){
            return null;
        }
        $this->assertValidKey($key, $user, $page);

        return (int)$key;
    }


    /**
     * Return all available concrete items
     *
     * @return Collection|int[]
     */
    public function getAllPossibleItems(): Collection
    {
        return $this->workTypeIds->shallowClone()->offsetSet(Definition::KEY_NONE, null);
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        if ($key !== Definition::KEY_NONE && !$this->workTypeIds->offsetExists($key)) {
            throw new InvalidArgumentException("No WorkType found for key: $key");
        }
    }
}
