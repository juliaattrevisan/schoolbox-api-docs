<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\YearDue;

use Schoolbox\Filter;


/**
 * Available options:
 */
class Definition extends Filter\AbstractDefinition
{

    // The name to expose in the UI
    const NAME = 'Year Due';


    /**
     * @inheritdoc
     */
    protected function getCustomKeys()
    {
        return [];
    }


    /**
     * @inheritdoc
     */
    public static function getPages()
    {
        return [];
    }


    /**
     * @inheritdoc
     */
    public function getLabel($key)
    {
        return $key;
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
        // by default, all WeightingTypes are available
        return true;
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir()
    {
        return '@filterDefinitions/Assessment/YearDue/templates';
    }
}
