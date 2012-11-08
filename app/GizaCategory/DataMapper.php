<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class GizaCategory_DataMapper extends Generic_DataMapper {

    const TABLE = 'category';

    // constructor is your chance to add your custom mappings for this object
    public function __construct() {

        // initialise query object
        parent::__construct();

        // declare fields and tables (FROM is specified automatically from the TABLE constant)
        $this->select('category.id');
        $this->select('category.name');
        $this->select('category.parent', 'parent_id');

        $this->select('GROUP_CONCAT(`categoryCode`.`code`)', 'code');

        $this->leftJoin('categoryCode', 'category.id', 'categoryCode.categoryID');
        $this->group('category.id');

        // specify fields that can by hydrated
        $this->setHydrateField('children');
        $this->setHydrateField('homepage');
        $this->setHydrateField('teacher');
    }

    /*
    // do pre-read manipulation here before calling the parent read, but don't call query functions in these methods,
    // it will only break things later down the track.
    public function read($conditions = '', $limit = 0) {
        return parent::read($conditions, $limit);
    }
    */

    public function readUserCategories($userID, $args) {
        // build the query to find all the categories a user is in

        $this->leftJoin('userAccess', 'userAccess.sid', 'category.id');

        $this->clear('where');
        $this->where('userAccess.service', '=', $args['service']);
        $this->where('userAccess.type', '>=', $args['access']);
        $this->where('userAccess.uid', '=', $userID);

        $this->group('category.id');
        $this->order('category.name');

        return $this->read('', 0, false);
    }
    
    public function getCategoriesByID($codes) {
        $this->clear();

        $this->select('name');
        $this->select('id');
        $this->select('categoryCode.code');
        $this->join('', 'categoryCode', 'category.id','categoryCode.categoryID');
        $this->where('categoryCode.code', 'IN', $codes);
    }

    public function hydrateHomepage(GizaCategory $instance, $args = '') {
        $hMap = new Facade_Homepage_DataMapper();
        $page = $hMap->read($instance->get('id'));

        $instance->set('homepage', $page);
    }

    public function hydrateTeacher(GizaCategory $instance, $args = '') {
        $eClass = new External_Class();
        if($instance->__isset('code')) {
            $tchr = $eClass->getTeacherName($instance->get('code'));
        }
        else {
            $tchr = null;
        }
        $uMap = new GizaUser_DataMapper();

        if(is_array($tchr) && count($tchr)) {
            $coll = new GizaCollection();
            foreach($tchr as $t) {
                try {
                    $u = $uMap->read(array('synergy_id' => $t));
                    $coll->add($u);
                }
                catch(Exception_Schoolbox $e) {
                    // do nothing
                }
            }
            $instance->set('teacher', $coll);
        }
    }
}
