<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\UnitImpl;

use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\GenericCollection;

/**
 * List of unit impls, grouped by Course Impl
 *
 * An assessment will only match a checked unitImpl if it is an assessment impl.
 *
 * □ None (not in a UnitImpl)
 *
 * * CourseImpl A
 * * □ UnitImpl 111 (will match any assessment impls that reside in UnitImpl 111)
 * * □ UnitImpl 222
 *
 * * CourseImpl B
 * * □ UnitImpl 333
 */
class Definition extends AbstractFilterDefinition
{
    const SLUG = 'ui';

    const KEY_NONE = 'none';        // Assessment is not inside a unitImpl


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
            return 'None (assessment does reside inside a unit implementation)';
        }
        throw new \InvalidArgumentException("Unknown 'UnitImpl' filter key: $key");
    }
}
