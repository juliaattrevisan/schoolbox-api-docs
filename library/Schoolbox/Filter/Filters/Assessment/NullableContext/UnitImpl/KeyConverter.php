<?php
/**
 * Author: andrewryantech@gmail.com
 * Created: 2/04/17 3:15 PM
 */

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\UnitImpl;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\NullableContext;
use Schoolbox\GenericCollection;

/**
 * No conversion. A int is converted to the same int (representing a unitSpec.id)
 * @method null|Collection|int[] getSelectedItems(GenericCollection $availableSelections = null)
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
     * @param string $key
     * @return null|int
     * @throws \InvalidArgumentException
     */
    public function getItem($key)
    {
        if(Definition::KEY_NONE === $key){
            return null;
        }
        $this->assertValidKey($key);
        return $this->unitImplIds[$key];
    }


    /**
     * Return all available concrete items
     *
     * @return Collection|int[]
     */
    public function getAllPossibleItems()
    {
        return $this->unitImplIds->shallowClone();
    }


    /**
     * @inheritdoc
     */
    public function sanitiseKey($key)
    {
        if(Definition::KEY_NONE === $key){
            return $key;
        }
        $this->assertValidKey($key);
        return (string)(int)$key;
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey($key)
    {
        if(Definition::KEY_NONE === $key){
            return;
        }
        if (!$this->unitImplIds->offsetExists($key)) {
            throw new \InvalidArgumentException("No UnitImpl ID found for key: $key");
        }
    }
}
