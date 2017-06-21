<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\WorkType;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;


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
    public function getItem($key)
    {
        $this->assertValidKey($key);
        if(Definition::KEY_NONE === $key){
            return null;
        }
        return (int)$key;
    }


    /**
     * Return all available concrete items
     *
     * @return Collection|int[]
     */
    public function getAllPossibleItems()
    {
        return $this->workTypeIds->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey($key)
    {
        if ($key !== Definition::KEY_NONE && !$this->workTypeIds->offsetExists($key)) {
            throw new \InvalidArgumentException("No WorkType found for key: $key");
        }
    }
}
