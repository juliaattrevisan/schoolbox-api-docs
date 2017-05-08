<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Unit;

use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\GenericCollection;

/**
 * Displays a set of checkboxes, one per UnitSpec, grouped by CourseSpec, eg:
 *
 * * □ On Folder
 *
 * * CourseSpec A
 * * □ UnitSpec 111
 * * □ UnitSpec 222
 *
 * * CourseSpec B
 * * □ UnitSpec 333
 *
 * We still need to retain 'On Folder' to enable a full set of options for this filter
 */
class Definition extends AbstractFilterDefinition
{
    /* Show units imported to the following folder types:
     * eg,
     * * Unit A imported to Archery(group) + Math3/4(class)
     * * Unit B imported to Archery(group)
     * * Unit C imported to Math3/4(class)
     *
     * If admin only selects 'classes', then user will see Unit A + Unit C (will not see Unit B)
     *
     * Items created in UnitImpls on a class will be included in 'CLASS_UNITS
     *
     * 'ON_FOLDER' => assessment is on folder (or in project on folder)
     */
    const KEY_ON_FOLDER         = 'on_folder';
    const KEY_IMPORTED_TO_CLASS = 'to_classes';
    const KEY_IMPORTED_TO_GROUP = 'to_groups';



    /**
     * @inheritdoc
     */
    protected function insertDynamicOptions(GenericCollection $pageOptions, $page)
    {
        // noop - abstract options are hard-coded, concrete options are run-time
        return $pageOptions;
    }


    /**
     * @inheritdoc
     */
    protected function unsetInvalidOptions(GenericCollection $pageOptions)
    {
        // noop - abstract options are hard-coded, concrete options are run-time
        return $pageOptions;
    }


    /**
     * @inheritdoc
     */
    protected function getInitialSelectedState($key, $page, $roleType)
    {
        // Nothing is selected, meaning filter is not applied
        return false;
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys()
    {
        return [
            self::KEY_ON_FOLDER,
            self::KEY_IMPORTED_TO_CLASS,
            self::KEY_IMPORTED_TO_GROUP,
        ];
    }


    /**
     * @inheritdoc
     */
    protected function sortFilterOptions(GenericCollection $options)
    {
        return $options->sortByCallback(function (Option\Model $left, Option\Model $right) {
            $leftKey  = $left->getKey();
            $rightKey = $right->getKey();
            if ($leftKey === $rightKey) {
                return 0;
            }
            if(self::KEY_ON_FOLDER === $leftKey){
                return -1;
            }
            return self::KEY_IMPORTED_TO_CLASS === $leftKey ? -1 : +1;
        });
    }


    /**
     * @inheritdoc
     */
    public function getInitialAvailability($customAbstractKey, $page)
    {
        // Initial config is both classes and groups are available for all pages
        return true;
    }


    /**
     * @inheritdoc
     */
    public static function getPages()
    {
        return [
            Option\Model::PAGE_MARK_BOOK,

            Option\Model::PAGE_PENDING_ASSESSMENTS
        ];
    }


    /**
     * @inheritdoc
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
        switch ($key) {
            case self::KEY_ON_FOLDER:
                return 'No Unit';
            case self::KEY_IMPORTED_TO_CLASS:
                return 'Units imported to classes';
            case self::KEY_IMPORTED_TO_GROUP:
                return 'Units imported to groups';
        }
        throw new \InvalidArgumentException("Unknown 'UnitSpec' filter key: $key");
    }
}
