<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\FolderYearLevel;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\Filter\Filters\Assessment\WorkType\Definition;
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
     * @return string
     */
    protected function getItem(string $key, User $user, string $page): ?int
    {
        return Definition::KEY_NONE === $key ? null : (int)$key;
    }


    /**
     * @return Collection|int[]|null[]
     */
    public function getAllPossibleItems(): Collection
    {
        return $this->yearLevelIds->shallowClone()->offsetSet(Definition::KEY_NONE, null);
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
