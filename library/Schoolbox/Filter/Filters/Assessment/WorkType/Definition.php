<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\WorkType;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\WorkType;


/**
 * Available options:
 * □ None
 * □ One per workType that exists in system
 */
class Definition extends Filter\AbstractDefinition
{

    // The name to expose in the UI
    const NAME = 'Work Type';

    // If assessment does not have a WorkType at all
    const KEY_NONE = 'none';

    /**
     * Intentionally not a GenericCollection so we can include null (ie assessment does not have a workType at all)
     *
     * @var Collection|WorkType\Model[]
     */
    private $workTypes;


    /**
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param Collection                         $workTypes
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
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
            Filter\Page\Model::PAGE_PENDING_ASSESSMENTS,
            Filter\Page\Model::PAGE_GRADES,
            Filter\Page\Model::PAGE_MARK_BOOK,
//            Filter\Page\Model::PAGE_DUE_WORK_CALENDAR,
        ];
    }


    /**
     * @param GenericCollection|Filter\Page\Option\Model[] $options
     * @return GenericCollection
     */
    protected function sortFilterOptions(GenericCollection $options)
    {
        // Keys represent WorkType IDs
        return $options->sortByCallback(function (Filter\Page\Option\Model $left, Filter\Page\Option\Model $right) {
            $leftKey  = $left->getKey();
            $rightKey = $right->getKey();
            if ($leftKey === $right) {
                return 0;
            }
            if (self::KEY_NONE === $leftKey) {
                return -1;
            }
            if (self::KEY_NONE === $rightKey) {
                return +1;
            }
            // Sort concrete items by workType.id
            return $leftKey - $rightKey;
        }, true);
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
    protected function insertDynamicOptions(Filter\Page\Model $pageFilter)
    {
        $options = $pageFilter->getOptions();

        // Ensure it contains all WorkTypes
        foreach ($this->workTypes as $workType) {

            $abstractKey = null === $workType ? self::KEY_NONE : (string)$workType->getId();

            if (!$options->offsetExists($abstractKey)) {
                $option = $this->getFilterOptionMapper()->createModel();
                $option
                    ->setFilterPage($pageFilter)
                    ->setKey($abstractKey)
                    ->setAvailable($this->getInitialAvailability($abstractKey, $pageFilter));
                $options->setOffset($option->getKey(), $option);
            }
        }
        return $options;
    }


    /**
     * @inheritdoc
     */
    protected function getInitialSelectedState($key, $page, $roleType)
    {
        // No options checked
        return false;
    }


    /**
     * @inheritdoc
     */
    public function getInitialAvailability($customAbstractKey, Filter\Page\Model $pageFilter)
    {
        // by default, all WorkTypes are available
        return true;
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir()
    {
        return '@filterDefinitions/Assessment/WorkType/templates';
    }
}
