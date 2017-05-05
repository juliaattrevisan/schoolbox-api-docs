<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\UnitSpec;

use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\GenericCollection;

/**
 * List of unit specs, grouped by Course
 *
 * An assessment will only match a checked unitSpec if it is an assessment spec.
 *
 * □ None (not in a UnitSpec)
 *
 * * CourseSpec A
 * * □ UnitSpec 111 (will match any assessment specs that reside in UnitSpec 111)
 * * □ UnitSpec 222
 *
 * * CourseSpec B
 * * □ UnitSpec 333
 */
class Definition extends AbstractFilterDefinition
{
    const SLUG = 'us';

    const KEY_NONE = 'none';        // Assessment is not inside a unitSpec


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
            self::KEY_NONE,
        ];
    }



    /**
     * @inheritdoc
     */
    public function getInitialAvailability($customAbstractKey, $page)
    {
        return true;
    }


    /**
     * @inheritdoc
     */
    public static function getPages()
    {
        // Does not appear on any pages yet - exists for now just to enable query building
        return [];
    }



    /**
     * @inheritdoc
     */
    public function getLabel($key)
    {
        if(self::KEY_NONE === $key){
            return 'None (assessment does reside inside a unit specification)';
        }
        throw new \InvalidArgumentException("Unknown 'UnitSpec' filter key: $key");
    }
}
