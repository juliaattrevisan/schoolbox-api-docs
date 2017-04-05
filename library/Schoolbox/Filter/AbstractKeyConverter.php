<?php
/**
 * Author: andrewryantech@gmail.com
 * Created: 2/04/17 4:30 PM
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;


abstract class AbstractKeyConverter implements IKeyConverter
{

    /** @var Filter\Model */
    private $filter;


    /**
     * @param Filter\Model $filter
     */
    public function __construct(Filter\Model $filter)
    {
        $this->filter = $filter;
    }


    /**
     * @return Collection (intentionally not a GenericCollection)
     */
    abstract protected function getItems();


    /**
     * Fetch the item represented by the key
     *
     * @param string $key
     * @return mixed
     * @throws \InvalidArgumentException If key is invalid
     */
    abstract protected function getItem($key);


    /**
     * @inheritdoc
     */
    public function getSelectedItems(GenericCollection $selections)
    {
        $availableItems = $this->getItems();
        $selectedItems  = $availableItems->emptyClone();

        foreach ($selections as $selection) {
            $item = $this->getItem($selection->getKey());

            if ($selection->getSelected()) {
                $selectedItems->add($item);
            }
        }
        // Were no items selected ?
        if (0 === $selectedItems->count()) {
            $selectedItems = $availableItems->shallowClone();
        }
        return $selectedItems;
    }


    /**
     * @param string $key A concrete key
     * @throws \InvalidArgumentException If key is invalid
     */
    abstract protected function assertValidKey($key);

    /* Getters
     */

    /**
     * @return Filter\Model
     */
    public function getFilter()
    {
        return $this->filter;
    }

}
