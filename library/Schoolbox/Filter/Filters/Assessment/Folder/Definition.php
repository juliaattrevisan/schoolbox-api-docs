<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder;

use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\Filter;

abstract class Definition extends AbstractDefinition
{
    const KEY_CLASSES = 'classes';
    const KEY_GROUPS = 'groups';

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
        // Initial config is both classes and groups are available for all pages
        return true;
    }
}
