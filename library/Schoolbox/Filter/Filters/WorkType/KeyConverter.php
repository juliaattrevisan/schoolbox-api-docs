<?php
/**
 * Author: andrewryantech@gmail.com
 * Created: 2/04/17 3:15 PM
 */

namespace Schoolbox\Filter\Filters\WorkType;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\WorkType;

/**
 * Converts between concrete keys (workType.ids) and WorkType models
 * @method GenericCollection|WorkType\Model[] getSelectedItems(GenericCollection $availableSelections)
 */
class KeyConverter extends AbstractKeyConverter
{

    /**
     * All WorkTypes known to the system (regardless of whether they are available)
     *
     * @var GenericCollection|WorkType\Model[]
     */
    private $workTypes;


    /**
     * @param Filter\Model $filter
     * @param Collection   $workTypes
     */
    public function __construct(Filter\Model $filter, Collection $workTypes)
    {
        parent::__construct($filter);
        $this->workTypes = $workTypes->shallowClone();
    }


    /**
     * Given a concrete key (such as returned from a user's filter panel selections), return the corresponding item
     *
     * @param string $key
     * @return mixed|WorkType\Model
     * @throws \InvalidArgumentException
     */
    public function getItem($key)
    {
        $this->assertValidKey($key);

        return $this->workTypes[$key];
    }


    /**
     * Return all available concrete items
     *
     * @return GenericCollection|WorkType\Model[]
     */
    public function getAllPossibleItems()
    {
        return $this->workTypes->shallowClone();
    }


    /**
     * @inheritdoc
     */
    public function sanitiseKey($key)
    {
        $this->assertValidKey($key);
        if(in_array($key, $this->workTypes->keys(), true)){
            return (string)$key;
        }
        return (string)(int)$key;
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey($key)
    {
        if ($key !== Definition::KEY_NONE && !$this->workTypes->offsetExists($key)) {
            throw new \InvalidArgumentException("No WorkType found for key: $key");
        }
    }
}
