<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\TemporalState;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\GenericCollection;


/**
 * Converts between TemporalState concrete keys and TemporalState strings
 * @method null|Collection|string[] getSelectedItems(GenericCollection $availableSelections = null)
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
     */
    protected function getItem($key)
    {
        $this->assertValidKey($key);
        return $key;
    }


    /**
     * @return Collection|string[]
     */
    public function getAllPossibleItems()
    {
        return $this->temporalStates->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey($key)
    {
        if (!in_array($key, $this->temporalStates->getAll(), true)) {
            throw new \InvalidArgumentException('TemporalState Key is invalid: ' . $key);
        }
    }

}
