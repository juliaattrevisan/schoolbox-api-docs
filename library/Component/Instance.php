<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
class Component_Instance extends Model {

    /**
     * @var string Contains custom table name
     */
    public static $_table = 'component_instance';

    /**
     * @static
     * @param ORM $orm
     * @param String $foreignTable
     * @param Integer $foreignID
     * @return ORM
     */
    public static function context (ORM $orm, $foreignTable, $foreignID) {
        return $orm->where('foreign_table', $foreignTable)
                   ->where('foreign_id', $foreignID)
                   ->order_by_asc('position')
                   ->order_by_asc('order');
    }
}