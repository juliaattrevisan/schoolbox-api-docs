<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class GizaUser_DataMapper extends Generic_DataMapper {

    // define the base table of this object
    const TABLE = 'user';
    const INDEX = 'user.id';

    // constructor is your chance to add your custom mappings for this object
    public function __construct() {

        // initialise query object
        parent::__construct();

        // declare fields and tables (FROM is specified automatically from the TABLE constant)
        $this->select('user.id', 'uid');
        $this->select('title');
        $this->select('firstname');
        $this->select('lastname');
        $this->select('username');
        $this->select('group_id');
        $this->select('year');
        $this->select('eportfolio_enabled');
        $this->select('synergy_id');
        $this->select('local_email');
        $this->select('alt_email');
        $this->select('email_pass');
        $this->select('userCampus.campus', 'campus_id');
        $this->select('luCampus.name', 'campus_name');
        $this->select('IF(`superusers`.`id`, 1, 0)', 'su');
        $this->select('house', 'house_id');
        $this->select('residential_house_id');
        $this->select('mail_handler');

        // specifiy foreign tables in this object
        $this->leftJoin('groups', 'user.group_id', 'groups.id');
        $this->leftJoin('userCampus', 'user.id', 'userCampus.uid');
        $this->leftJoin('luCampus', 'userCampus.campus', 'luCampus.id');
        $this->leftJoin('superusers', 'user.id', 'superusers.id');

        // specify fields that can by hydrated
        $this->setHydrateField('group');
        $this->setHydrateField('groups');
        $this->setHydrateField('campus');
        $this->setHydrateField('house');
        $this->setHydrateField('residential_house');
        $this->setHydrateField('subjects');
        $this->setHydrateField('timetable');
    }



    public function hydrateGroup(GizaUser $user, $args = '') {
        $gMap = new GizaGroup_DataMapper();
        $user->set('group', $gMap->read($user->get('group_id')));
    }


    public function hydrateCampus(GizaUser $user, $args = '') {

        $cMap = new Campus_DataMapper();
        $campuses = $cMap->getCampusMembership($user->get('uid'));

        $user->set('campus', $campuses);
    }


    /**
     * Hydrates the user with their external subjects
     * @param GizaUser $user
     * @param $args
     */
    public function hydrateSubjects(GizaUser $user, $args = array('service' => HOMEPAGE_SERVICE, 'access' => READ_ACCESS)) {
        /* EXTERNAL SUBJECT DEFINTIONS */
        try {
            $euMap = new External_User_DataMapper();
            $euMap->getCurrentSemester();
            $foo = $euMap->getSubjectList($user->get('synergy_id'), $user);
            $user->set('subjects', $foo);
        }
        catch(Exception_Schoolbox $e) {
            // couldn't hydrate, probably because there's nothing there, so just set the subjects to a blank collection
            $user->set('subjects', new GizaCollection());
        }
    }

    /**
     * Hydrates the user with their internal categories
     * @param GizaUser $user
     * @param array $args
     */
    public function hydrateGroups(GizaUser $user, $args = array('service' => HOMEPAGE_SERVICE, 'access' => READ_ACCESS)) {
        /* INTERNAL CATEGORIES */
        try {
            $cMap = new GizaCategory_DataMapper();
            $cats = $cMap->readUserCategories($user->get('uid'), $args);
            $user->set('groups', $cats);
        }
        catch(Exception_Schoolbox $e) {
            // couldn't hydrate, probably because there's nothing there, so just set the subjects to a blank collection
            $user->set('subjects', new GizaCollection());
        }
    }
}
