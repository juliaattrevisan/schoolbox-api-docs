<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder;

use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\GenericCollection;

/**
 * List of folders. Not currently exposed in UI.
 *
 * An assessment will only match a checked folder only if it resides directly in that folder (not indirectly
 * via a project, unit-impl, or unit-spec)
 *
 * □ None (not directly in a folder)
 * □ English 3 4 (EN344)
 * □ Archery
 * □ ...
 */
class Definition extends AbstractFilterDefinition
{
    const KEY_NONE = 'none';        // Assessment is not directly inside a folder

    /**
     * @inheritdoc
     */
    public static function getQueryKey()
    {
        return 'fd';
    }


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
            return 'None (assessment does reside directly on a folder)';
        }
        throw new \InvalidArgumentException("Unknown 'Folder' filter key: $key");
    }
}
