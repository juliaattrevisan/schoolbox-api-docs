<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Notification\Parameter;

use Alaress\Async\Notification\Parameter\Param;
use Schoolbox\Resource\Folder\Model as Folder;

class CategoryParam extends Param
{

    /**
     * Use standard vars from the category object, and default to the append stacking mode
     *
     * @param Folder $category
     * @param int      $stacking
     */
    public function __construct($category, $stacking = self::STACKING_REPLACE)
    {
        parent::__construct($category->getName(), "/homepage/{$category->getId()}", $stacking);
    }
}
