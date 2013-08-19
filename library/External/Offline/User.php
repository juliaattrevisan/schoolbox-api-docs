<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Alaress\DebugBar\Instance as DebugBar;

class External_Offline_User implements External_UserInterface {

	public function getParent($externalUserId) {
		// TODO: Implement getParent() method.
	}

	public function getParentSync($externalUserId) {
		// TODO: Implement getParentSync() method.
	}

	public function getParentEmails($synergyIds) {
		// TODO: Implement getParentEmails() method.
	}

	public function getStudent($externalUserId) {
		// TODO: Implement getStudent() method.
	}

	public function getStaff($externalUserId) {
		// TODO: Implement getStaff() method.
	}

	public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form) {
		// TODO: Implement getStudentContactListExtended() method.
	}

	public function getStudentContactList($externalUserId) {
		// TODO: Implement getStudentContactList() method.
	}

	public function getCurrentSemester() {
		// TODO: Implement getCurrentSemester() method.
	}

	public function getSubjectList($externalUserId, $userId) {
		return array();
	}

	public function getEnrolledStudents($externalUserId) {
		// TODO: Implement getEnrolledStudents() method.
	}

	public function getEmail($externalUserId) {
		// TODO: Implement getEmail() method.
	}

	public function getPortrait($externalUserId) {
		// TODO: Implement getPortrait() method.
	}

	public function getStudentUserAccountInfo() {
		// TODO: Implement getStudentUserAccountInfo() method.
	}

	public function getParentUserAccountInfo() {
		// TODO: Implement getParentUserAccountInfo() method.
	}

	public function getStaffUserAccountInfo() {
		// TODO: Implement getStaffUserAccountInfo() method.
	}

	public function determineBoarderType($boarderValue) {
		// TODO: Implement determineBoarderType() method.
	}

	public function getHousemasterByHouseId($houseID) {
		// TODO: Implement getHousemasterByHouseId() method.
	}

}
