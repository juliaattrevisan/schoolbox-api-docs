<?php
/**
 * Author: andrewryantech@gmail.com
 * Created: 2/04/17 3:15 PM
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
    public function sanitiseKey($key)
    {
        $this->assertValidKey($key);
        return (string)$key;
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
