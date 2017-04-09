<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Selection;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('filter_selection');
        $this->hasModel(Model::class);

        $this->joins('filter_id', Filter\Mapper::class, 'id', self::TYPE_MODEL, null, 'filter', 'filter');
        $this->joins('user_id', UserMapper::class, 'id', self::TYPE_MODEL, null, 'user', 'user');
        $this->uses('page', self::TYPE_STRING);
        $this->uses('key', self::TYPE_STRING);
        $this->uses('selected', self::TYPE_BOOLEAN);
    }


    /**
     * Update the specified user's filter selections for the specified page
     * Returns the saved selections, grouped by filter.id, then indexed by selection.key
     *
     * @param GenericCollection|Filter\IKeyConverter[] $keyConverters         Indexed by filter.id
     * @param User                                     $user
     * @param string                                   $page
     * @param bool[][]                                 $filterSelectionsArray Indexed by filter.id, selection.key
     * @throws \InvalidArgumentException If a posted selection.key is invalid
     * @return Model[][]  Grouped by filter.id, then indexed by selection.key
     */
    public function updateSelections(GenericCollection $keyConverters, User $user, $page, array $filterSelectionsArray = [])
    {
        // Delete user's current selections for this page
        $this->deleteByColumnList([
            'user_id' => $user->getId(),
            'page'    => $page,
        ]);

        // Save User's current selections
        $selectionsToSave = $this->createCollection();

        /**
         * Parse the array data to build a graph of all user selections, grouped by filter.id
         * @var Collection|GenericCollection[]|Model[][] $selectionsPerFilter
         */
        $selectionsPerFilter = new Collection();

        // Has any data been passed in? (will be empty if no filters available on this page)
        if (count($filterSelectionsArray)){
            foreach ($filterSelectionsArray as $filterId => $filterSelectionArray) {
                $keyConverter = $keyConverters[$filterId];
                $filter       = $keyConverter->getFilter();

                $selectionsPerFilter->setOffset($filterId, $this->createCollection());

                /** @var bool[] $filterSelectionArray */
                foreach ($filterSelectionArray as $key => $isSelected) {
                    $selection = $this->createModel();
                    $selection
                        ->setFilter($filter)
                        ->setUser($user)
                        ->setPage($page)
                        ->setKey($keyConverter->sanitiseKey($key))
                        ->setSelected((bool)$isSelected);
                    $selectionsToSave->add($selection);
                    $selectionsPerFilter[$filterId]->offsetSet($key, $selection);
                }
            }
            $this->save($selectionsToSave);
        }
        return $selectionsPerFilter;
    }
}
