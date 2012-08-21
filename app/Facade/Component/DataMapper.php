<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Facade_Component_DataMapper extends Generic_DataMapper {

    const TABLE = 'homepage_comp_location';
    const INDEX = 'homepageComponentLocationID';

    public function __construct() {
        parent::__construct();

        $this->select('column');
        $this->select('position');
        $this->select('homepage_comp_location.config');
        $this->select('name');
        $this->select('method');
        $this->leftJoin('homepage_comp', 'homepage_comp_location.component', 'homepage_comp.id');
        $this->order('column', 'asc');
        $this->order('position','asc');
    }


}
