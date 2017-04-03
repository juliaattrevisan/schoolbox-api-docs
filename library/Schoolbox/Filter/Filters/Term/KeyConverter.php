<?php
/**
 * Author: andrewryantech@gmail.com
 * Created: 2/04/17 3:15 PM
 */

namespace Schoolbox\Filter\Filters\Term;

use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Term;


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
     * @param GenericCollection|Term\Model[] $terms
     */
    public function __construct(GenericCollection $terms)
    {
        // Ensure invoker cannot mutate our collection's sorting or indexing after construction
        $this->terms = $terms->shallowClone()->indexBy('id');
    }


    /**
     * Given a key (such as returned from a user's filter panel selections), return the corresponding item
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
     * For a given element, return its key
     *
     * @param Term\Model $element
     * @return string
     */
    public function getKey($element)
    {
        return (string)$element->getId();
    }
}
