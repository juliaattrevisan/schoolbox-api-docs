<?php
/**
 * Mapper for fetching category in SBFS.  These are also known as folders on the front end.
 * Categories are a heirachy tree that all other elements a linked with; pages, files, links, etc
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Resource;

/**
 * Class CategoryMapper
 *
 * @deprecated
 * @package Schoolbox\Resource
 */
class CategoryMapper extends Folder\Mapper
{

    /**
     * @deprecated
     * @return static
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }


    /**
     * @deprecated
     */
    public function __construct()
    {
        parent::__construct();
    }

}