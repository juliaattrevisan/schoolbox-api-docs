<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class GizaGroup_DataMapper extends Generic_DataMapper {

    // define the base table of this object
    const TABLE = 'groups';

    // constructor is your chance to add your custom mappings for this object
    public function __construct() {

        // initialise query object
        parent::__construct();

        // declare fields and tables (FROM is specified automatically from the TABLE constant)
        $this->select('id', 'gid');
        $this->select('name');
        $this->select('group_type');

        // specifiy foreign tables in this object
/*
        $this->join('LEFT', 'groups', 'user.group_id', 'groups.id');
        $this->join('LEFT', 'userCampus', 'user.id', 'userCampus.uid');
        $this->join('LEFT', 'luCampus', 'userCampus.campus', 'luCampus.id');
        $this->join('LEFT', 'superusers', 'user.id', 'superusers.id');
*/
        // specify fields that can by hydrated
        $this->setHydrateField('members');
    }
}
