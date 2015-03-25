<?php
/**
 * Stub mapper for categories.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Resource;

/**
 * Class Category
 *
 * @deprecated in favour of Resource\Folder\Model, this class retained for backward compatibility
 * @package Schoolbox\Resource
 */
class Category extends Folder\Model
{

    /**
     * @deprecated
     */
    public function __construct()
    {
        parent::__construct();
    }

}