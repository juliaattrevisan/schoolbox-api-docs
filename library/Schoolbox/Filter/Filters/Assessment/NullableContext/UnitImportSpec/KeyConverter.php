<?php
/**
 * Author: andrewryantech@gmail.com
 * Created: 2/04/17 3:15 PM
 */

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
        return $this->unitSpecIds[$key];
    }


    /**
     * Return all available concrete items
     *
     * @return Collection|int[]
     */
    public function getAllPossibleItems()
    {
        return $this->unitSpecIds->shallowClone();
    }



    /**
     * @inheritdoc
     */
    protected function assertValidKey($key)
    {
        if(Definition::KEY_NONE === $key){
            return;
        }
        if (!$this->unitSpecIds->offsetExists($key)) {
            throw new \InvalidArgumentException("No UnitSpec ID found for key: $key");
        }
    }
}
