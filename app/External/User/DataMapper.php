<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class External_User_DataMapper extends Generic_DataMapper implements External_User_Interface {

    protected $externalUser;

    // this class does not use internal SB tables
    const TABLE = '';

    // constructor is your chance to add your custom mappings for this object
    public function __construct() {
        $class = 'External_' . ucfirst(strtolower(Config::get('external_type'))) . '_User';
        $this->externalUser = new $class();
    }

    public function read($conditions = '', $limit = 0, $clearWhere = true) {

        try {
            return $this->externalUser->$conditions();
        }
        catch (ErrorException $e) {
            throw new Exception_Schoolbox("External User method call failed: " . $e->getMessage());
        }

    }

    // so much for dynamic data retrieval... -_-
    public function getSubjectList($externalUserId, GizaUser $user) {

	    // only bother to check if the current user is a student or staff; parents don't have subjects
		if(!in_array($user->getGroupType(), array(Group::STAFF_GROUP_TYPE, Group::STUDENT_GROUP_TYPE))) {
			return new GizaCollection();
		}

        $list = $this->getExternalUser()->getSubjectList($externalUserId, $user->get('uid'));

        $codes = array();

        if(is_array($list) && count($list)) {
            foreach($list as $subject) {
                $codes[] = $subject['code'];
            }
            // print_r($codes);
            if(count($codes)) {
                $cMap = new GizaCategory_DataMapper();
                return $cMap->read(array('categoryCode.code' => $codes));
            }
        }

        return new GizaCollection();
    }

    public function getParent($externalUserId) {
        return $this->getExternalUser()->getParent($externalUserId);
    }
    public function getParentSync($externalUserId) {
        return $this->getExternalUser()->getParentSync($externalUserId);
    }
    public function getStudent($externalUserId) {
        return $this->getExternalUser()->getStudent($externalUserId);
    }
    public function getStaff($externalUserId) {
        return $this->getExternalUser()->getStaff($externalUserId);
    }
    public function getStudentContactListExtended($yearLevel) {
        return $this->getExternalUser()->getStudentContactListExtended($yearLevel);
    }
    public function getStudentContactList($externalUserId) {
        return $this->getExternalUser()->getStudentContactList($externalUserId);
    }
    public function getCurrentSemester() {
        return $this->getExternalUser()->getCurrentSemester();
    }

    public function getEnrolledStudents($externalUserId) {
        return $this->getExternalUser()->getEnrolledStudents($externalUserId);
    }
    public function getEmail($externalUserId) {
        return $this->getExternalUser()->getEmail($externalUserId);
    }
    public function getPortrait($externalUserId) {
        return $this->getExternalUser()->getPortrait($externalUserId);
    }
    public function getStudentUserAccountInfo() {
        return $this->getExternalUser()->getStudentUserAccountInfo();
    }
    public function getParentUserAccountInfo() {
        return $this->getExternalUser()->getParentUserAccountInfo();
    }
    public function getStaffUserAccountInfo() {
        return $this->getExternalUser()->getStaffUserAccountInfo();
    }

    /**
     * @return External_User_Interface
     */
    protected function getExternalUser() {
        return $this->externalUser;
    }


    // this is a read-only database, so do not allow for any data manipulation
    public function create(Generic_Object $obj) { }
    public function update(Generic_Object $obj) { }
    public function delete(Generic_Object $obj) { }

}
