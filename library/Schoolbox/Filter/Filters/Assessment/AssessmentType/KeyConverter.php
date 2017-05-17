<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\AssessmentType;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\GenericCollection;


/**
 * Converts between AssessmentType concrete keys and AssessmentType strings
 * @method null|Collection|string[] getSelectedItems(GenericCollection $availableSelections = null)
 */
class KeyConverter extends AbstractKeyConverter
{

    /**
     * All AssessmentTypes known to the system (regardless of whether they are available)
     *
     * @var Collection|string[]
     */
    private $assessmentTypes;


    /**
     * @param Filter\Model        $filter
     * @param Collection|string[] $assessmentTypes
     */
    public function __construct(Filter\Model $filter, Collection $assessmentTypes)
    {
        parent::__construct($filter);
        $this->assessmentTypes = $assessmentTypes;
    }


//    /**
//     * @param string $item
//     * @return string
//     */
//    public function getKey($item)
//    {
//        return (string)$item;
//    }


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
        return $this->assessmentTypes->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey($key)
    {
        if (!in_array($key, $this->assessmentTypes->getAll(), true)) {
            throw new \InvalidArgumentException('AssessmentType Key is invalid: ' . $key);
        }
    }

}
