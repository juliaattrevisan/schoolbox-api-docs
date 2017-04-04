<?php
/**
 * Author: andrewryantech@gmail.com
 * Created: 2/04/17 3:15 PM
 */

namespace Schoolbox\Filter\Filters\AssessmentType;

use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Term;
use Schoolbox\Filter;


/**
 * Converts between termFilter concrete keys and Term models
 */
class KeyConverter extends AbstractKeyConverter
{

    /**
     * @var GenericCollection|Term\Model[]
     */
    private $terms;


    /**
     * @param Filter\Model $filter
     * @param GenericCollection|Term\Model[] $terms
     */
    public function __construct(Filter\Model $filter, GenericCollection $terms)
    {
        parent::__construct($filter);

        // Ensure invoker cannot mutate our collection's sorting or indexing after construction
        $this->terms = $terms->shallowClone()->indexBy('id');
    }


    /**
     * Given a concrete key (such as returned from a user's filter panel selections), return the corresponding item
     *
     * @param string $key
     * @return mixed|Term\Model
     * @throws \InvalidArgumentException
     */
    public function getItem($key)
    {
        if(!$this->terms->offsetExists($key)){
            throw new \InvalidArgumentException("No term found for key:  $key");
        }
        return $this->terms[$key];
    }


    /**
     * Return a concrete key for the specified element
     *
     * @param Term\Model $element
     * @return string
     */
    public function getKey($element)
    {
        return (string)$element->getId();
    }
}
