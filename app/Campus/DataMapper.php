<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Campus_DataMapper extends Generic_DataMapper {

    const TABLE = 'luCampus';

    public function __construct() {
        parent::__construct();

        $this->select('id');
        $this->select('name');
    }


    public function getCampusMembership($userID) {
        $this->clear();
        $this->select('campus');
        $this->select('defaultCampus', '`default`');
        $this->select('name');
        $this->leftJoin('userCampus','userCampus.campus','luCampus.id');
        $this->where('uid','=',$userID);
        $this->order('default', 'DESC');

        return $this->read('', 0, false);
    }
}
