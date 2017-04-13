<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\WorkType;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\WorkType;


/**
 * Available options:
 * * None
 * * One per configured workType
 */
class Definition extends AbstractFilterDefinition
{

    // If assessment does not have a WorkType at all
    const KEY_NONE = ' none';

    /**
     * Intentionally not a GenericCollection so we can include null (ie assessment does not have a workType at all)
     *
     * @var Collection|WorkType\Model[]
     */
    private $workTypes;


    /**
     * @param Filter\Model    $filter
     * @param Option\Mapper   $filterOptionMapper
     * @param Defaults\Mapper $filterDefaultMapper
     * @param Collection      $workTypes
     */
    public function __construct(
        Filter\Model $filter,
        Option\Mapper $filterOptionMapper,
        Defaults\Mapper $filterDefaultMapper,
        Collection $workTypes
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->workTypes = $workTypes;
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys()
    {
        return [
            self::KEY_NONE,
        ];
    }


    /**
     * @inheritdoc
     */
    public static function getPages()
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
            if ($leftKey === self::KEY_NONE) {
                return -1;
            }
            if ($leftKey === $right) {
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
        return array_merge($this->getCustomKeys(), $this->workTypes->keys());
    }


    /**
     * @inheritdoc
     */
    public function getLabel($key)
    {
        if (self::KEY_NONE === $key) {
            return '(none)';
        }
        return $this->workTypes[$key]->getName();
    }


    /**
     * @inheritdoc
     */
    protected function insertDynamicOptions(GenericCollection $pageOptions, $page)
    {
        // Ensure it contains all WorkTypes
        foreach ($this->workTypes as $workType) {

            $abstractKey = null === $workType ? self::KEY_NONE : (string)$workType->getId();

            if (!$pageOptions->offsetExists($abstractKey)) {
                $option = $this->getFilterOptionMapper()->createModel();
                $option
                    ->setFilter($this->getFilter())
                    ->setKey($abstractKey)
                    ->setPage($page)
                    ->setAvailable($this->getInitialAvailability($abstractKey, $page));
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
            $abstractKey = $filterOption->getKey();
            // Does option refer to a non-existent workType ? (eg if deleted)
            if ($abstractKey !== self::KEY_NONE && !$this->workTypes->offsetExists($abstractKey)) {
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
