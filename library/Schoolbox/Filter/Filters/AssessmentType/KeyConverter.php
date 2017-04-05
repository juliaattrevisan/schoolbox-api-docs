<?php
/**
 * Author: andrewryantech@gmail.com
 * Created: 2/04/17 3:15 PM
 */

namespace Schoolbox\Filter\Filters\AssessmentType;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\GenericCollection;


/**
 * Converts between termFilter concrete keys and AssessmentType strings
 * @method Collection|string[] getSelectedItems(GenericCollection $selections)
 */
class KeyConverter extends AbstractKeyConverter
{

    /**
     * @var Collection|string
     */
    private $assessmentTypes;


    /**
     * @param Filter\Model $filter
     * @param Collection|string[]   $assessmentTypes
     */
    public function __construct(Filter\Model $filter, Collection $assessmentTypes)
    {
        parent::__construct($filter);
        $this->assessmentTypes = $assessmentTypes;
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
    protected function getItems()
    {
        return $this->assessmentTypes->shallowClone();
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
        if(!in_array($key, $this->assessmentTypes->getAll(), true)){
            throw new \InvalidArgumentException('AssessmentType Key is invalid: ' . $key);
        }
    }

}
