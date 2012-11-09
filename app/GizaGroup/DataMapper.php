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

        // specify fields that can by hydrated
        $this->setHydrateField('members');
        $this->setHydrateField('dashboard');
    }

    public function hydrateDashboard(GizaGroup $group, $args = '') {
        $cMap = new Facade_Component_DataMapper();

        $components = $cMap->read(array(
            'component_instance.module' => 'dashboard',
            'component_instance.identifier' => $group->get('gid')
        ));

        // force hydration of configs
        foreach($components as $c) {
            $c->get('config');
        }

        $group->set('dashboard', $components);
    }

    public function hydrateMembers(GizaGroup $group, $args = '') {
        $uMap = new GizaUser_DataMapper();
        $collection = $uMap->read(array('group_id' => $group->get('gid')));
        $group->set('members', $collection);
    }
}
