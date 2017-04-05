<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\WorkType;

use Schoolbox\Filter;
use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\WorkType;


/**
 * Available options:
 * * Project
 * * Quiz
 * * DueWork
 * * Task
 *
 * Defaults to full set, but run-time options will enable controller to exclude individual items
 *
 */
class Definition extends AbstractFilterDefinition
{
    /** @var GenericCollection|WorkType\Model[] */
    private $workTypes;


    /**
     * @param Filter\Model      $filter
     * @param Option\Mapper     $filterOptionMapper
     * @param Defaults\Mapper   $filterDefaultMapper
     * @param GenericCollection $workTypes
     */
    public function __construct(
        Filter\Model $filter,
        Option\Mapper $filterOptionMapper,
        Defaults\Mapper $filterDefaultMapper,
        GenericCollection $workTypes
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->workTypes = $workTypes;
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys()
    {
        // No custom keys - all are pulled dynamically
        return [];
    }



    /**
     * @inheritdoc
     */
    public function getPages()
    {
        return [
            Option\Model::PAGE_PENDING_ASSESSMENTS,
            Option\Model::PAGE_GRADES,
            Option\Model::PAGE_MARK_BOOK,
            Option\Model::PAGE_DUE_WORK_CALENDAR,
        ];
    }


    /**
     * @param GenericCollection|Option\Model[] $options
     * @return GenericCollection
     */
    protected function sortFilterOptions(GenericCollection $options)
    {
        // Keys represent WorkType IDs

        return $options->sortByCallback(function (Option\Model $left, Option\Model $right) {
            $leftKey  = $left->getKey();
            $rightKey = $right->getKey();
            if($leftKey === $right){
                return 0;
            }
            return $leftKey < $rightKey ? -1 : +1;
        });
    }


    /**
     * Return the set of possible keys for this filter's options
     *
     * @return string[]
     */
    public function getAbstractKeys()
    {
        return $this->getCustomKeys();
    }


    /**
     * @inheritdoc
     */
    public function getLabel($key)
    {
        return $this->workTypes[$key]->getName();
    }


    /**
     * @inheritdoc
     */
    protected function insertDynamicOptions(GenericCollection $pageOptions, $page)
    {
        // Ensure it contains all WorkTypes
        foreach ($this->workTypes as $workType) {
            if (!$pageOptions->offsetExists($workType->getId())) {
                $option = $this->getFilterOptionMapper()->createModel();
                $key = (string)$workType->getId();
                $option
                    ->setFilter($this->getFilter())
                    ->setKey($key)
                    ->setPage($page)
                    ->setAvailable($this->getInitialAvailability($key, $page));
                $pageOptions->setOffset($option->getKey(), $option);
            }
        }
        return $pageOptions;
    }


    /**
     * @inheritdoc
     */
    protected function unsetInvalidOptions(GenericCollection $pageOptions)
    {
        foreach ($pageOptions as $filterOption) {
            // Does option refer to a non-existent workType ? (eg if deleted)
            if (!$this->workTypes->offsetExists($filterOption->getKey())) {
                $pageOptions->offsetUnset($filterOption->getKey());
            }
        }
        return $pageOptions;
    }


    /**
     * @inheritdoc
     */
    protected function getInitialSelectedState($key, $page, $roleType)
    {
        // by default, all are not selected (ie the filter is not applied)
        return false;
    }


    /**
     * @inheritdoc
     */
    protected function getInitialAvailability($customAbstractKey, $page)
    {
        // by default, all WorkTypes are available
        return true;
    }
}
