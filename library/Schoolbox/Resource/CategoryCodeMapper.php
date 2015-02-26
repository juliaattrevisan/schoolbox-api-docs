<?php
/**
 * Categories may have one or more codes that link them to the SIS backend.  These codes are used as the primary key
 * to fetch data from the External SIS.  They are optional and not all categories will have codes.  These codes drive
 * class lists and the My Subjects and Timetable links.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Resource;

/**
 * Class CategoryCodeMapper
 *
 * @deprecated
 * @package Schoolbox\Resource
 */
class CategoryCodeMapper extends Folder\Code\Mapper
{

    /**
     * @deprecated
     */
    public function __construct()
    {
        parent::__construct();
    }

}