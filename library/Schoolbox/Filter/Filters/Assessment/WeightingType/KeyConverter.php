<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\WeightingType;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;


/**
 * Converts between Weighting concrete keys and Weighting strings
 */
class KeyConverter extends AbstractKeyConverter
{

    /**
     * All 'hasWeighting' values known to the system (regardless of whether they are available)
     *
     * Simply the set ['weighted','unweighted']
     *
     * @var Collection|string[]
     */
    private $weightingTypes;


    /**
     * @param Filter\Model        $filter
     * @param Collection|string[] $weightingTypes
     */
    public function __construct(Filter\Model $filter, Collection $weightingTypes)
    {
        parent::__construct($filter);
        $this->weightingTypes = $weightingTypes;
    }


    /**
     * @inheritdoc
     * @return string
     */
    protected function getItem(string $key, \Schoolbox\User $user, string $page): string
    {
        $this->assertValidKey($key, $user, $page);
        return $key;
    }


    /**
     * @return Collection|string[]
     */
    public function getAllPossibleItems(): Collection
    {
        return $this->weightingTypes->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, \Schoolbox\User $user, string $page): void
    {
        if (!in_array($key, $this->weightingTypes->getAll(), true)) {
            throw new \InvalidArgumentException('WeightingType Key is invalid: ' . $key);
        }
    }

}
