<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\WeightingType;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\GenericCollection;


/**
 * Converts between Weighting concrete keys and Weighting strings
 * @method null|Collection|string[] getSelectedItems(GenericCollection $availableSelections = null)
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
        return $this->weightingTypes->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey($key)
    {
        if (!in_array($key, $this->weightingTypes->getAll(), true)) {
            throw new \InvalidArgumentException('WeightingType Key is invalid: ' . $key);
        }
    }

}
