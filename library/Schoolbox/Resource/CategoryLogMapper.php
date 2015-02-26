<?php
/**
 * Mapper for category log entries.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Resource;

/**
 * Class CategoryLogMapper
 *
 * @deprecated
 * @package Schoolbox\Resource
 */
class CategoryLogMapper extends Folder\Log\Mapper
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