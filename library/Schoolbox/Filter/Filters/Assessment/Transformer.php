<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment;

use Schoolbox\Collection;

/**
 * The Assessment Transformer is specialised because assessments have a coupling between the 'folder' filter
 * and the 'nullable-context' filters.
 *
 * When the folder selections are present, we must alter the behaviour of the 'nullable-context' filters when they have nothing selected.
 *
 * When folder selections are present, and the 'nullable-context' filter represents a sub-set of all entities, and no 'nullable-context' filter
 * options are checked, then we can treat the 'nullable-context' filter as not being applied (rather than the sub-set listed), and just set it to NULL.
 *
 * This is because assessments will still be filtered by the folder filter, which ultimately sits underneath the 'nullable-context' filters and
 * implicitly reduces the set of nullable-context selections because of the folder-selections.
 */
class Transformer extends \Schoolbox\Filter\Transformer
{

    /**
     * Either 'null', meaning the filter is not being used, or a Collection of integers representing folder IDS
     *
     * @var |null|Collection|int[]
     */
    private $folderSelections;


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException
     */
    public function __construct($keyConverters, $selectionsPerFilter)
    {
        parent::__construct($keyConverters, $selectionsPerFilter);
        $this->folderSelections = $this->getSelectedItems(Folder\Definition::class);
    }


    /**
     * Get a set of entities represented by a filter component's selections
     *
     * @param string $filterClass
     * @return null|Collection|mixed[]
     * @throws \InvalidArgumentException
     */
    public function getSelectedItems($filterClass)
    {
        if (!$this->getFiltersByClass()->offsetExists($filterClass)) {
            throw new \InvalidArgumentException("No KeyConverter found for filter class: $filterClass");
        }
        $instance = $this->getFiltersByClass()[$filterClass];
        $keyConverter = $this->getKeyConverters()[$instance->getId()];

        // Is this a 'nullable-context' assessment filter
        $nullableContexts = [
            NullableContext\Project\Definition::class,
            NullableContext\UnitImpl\Definition::class,
            NullableContext\UnitImportSpec\Definition::class,
            NullableContext\UnitSpec\Definition::class,
        ];
        if(in_array($filterClass, $nullableContexts, true)){
            $foldersFiltered = null !== $this->folderSelections;
            /** @var NullableContext\KeyConverter $keyConverter */
            return $keyConverter->getSelectedItems($this->getSelectionsPerFilter()[$instance->getId()], $foldersFiltered);
        }
        return $keyConverter->getSelectedItems($this->getSelectionsPerFilter()[$instance->getId()]);
    }
}
