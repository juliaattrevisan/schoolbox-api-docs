<?php
/**
 * Author: andrewryantech@gmail.com
 * Created: 2/04/17 3:15 PM
 */

namespace Schoolbox\Filter\Filters\Term;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Term;
use Schoolbox\Filter;


/**
 * Converts between termFilter concrete keys and Term models
 * @method GenericCollection|Term\Model[] getSelectedItems(GenericCollection $availableSelections)
 */
class KeyConverter extends AbstractKeyConverter
{

    /**
     * All Terms in the system (regardless of whether they are available)
     *
     * @var GenericCollection|Term\Model[]
     */
    private $terms;


    /**
     * @param Filter\Model                   $filter
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
    protected function getItem($key)
    {
        $this->assertValidKey($key);
        return $this->terms[$key];
    }


    /**
     * Return all available concrete items
     *
     * @return Collection|GenericCollection|Term\Model[]
     */
    public function getAllPossibleItems()
    {
        return $this->terms->shallowClone();
    }


    /**
     * @inheritdoc
     */
    public function sanitiseKey($key)
    {
        $this->assertValidKey($key);
        return (string)(int)$key;
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey($key)
    {
        if (!$this->terms->offsetExists($key)) {
            throw new \InvalidArgumentException("No term found for key:  $key");
        }
    }
}
