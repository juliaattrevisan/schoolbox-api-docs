<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\TemporalState;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;


/**
 * Converts between TemporalState concrete keys and TemporalState strings
 */
class KeyConverter extends AbstractKeyConverter
{

    /**
     * All 'TemporalState' values known to the system (regardless of whether they are available)
     *
     * @var Collection|string[]
     */
    private $temporalStates;


    /**
     * @param Filter\Model        $filter
     * @param Collection|string[] $temporalStates
     */
    public function __construct(Filter\Model $filter, Collection $temporalStates)
    {
        parent::__construct($filter);
        $this->temporalStates = $temporalStates;
    }


    /**
     * @inheritdoc
     * @return string
     * @throws \InvalidArgumentException
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
        return $this->temporalStates->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, \Schoolbox\User $user, string $page): void
    {
        if (!in_array($key, $this->temporalStates->getAll(), true)) {
            throw new \InvalidArgumentException('TemporalState Key is invalid: ' . $key);
        }
    }

}
