<?php
/**
 * Synchronise_External_Donvale_User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Donvale_User extends Synchronise_External_Donvale_Abstract {
	private $studentColumnList = array('StudId', 'Password', 'StudFirstName', 'StudLastName', 'Guardian1', 'Guardian2', 'StudAddress', 'Guardian1Tel_H', 'Guardian1Tel_W', 'Guardian1Tel_M', 'Guardian2Tel_H', 'Guardian2Tel_W', 'Guardian2Tel_M', 'Guardian1Add', 'Guardian2Add', 'Guardian1Sub', 'Guardian2Sub', 'Guardian1PostC', 'Guardian1Email', 'Guardian2Email', 'Guardian2PostC', 'Guardian1MailAdd', 'Guardian2MailAdd', 'DateofBirth', 'StudTel', 'StudMob', 'Sex', 'Suburb', 'PostCode', 'EnrolmentYear', 'HomeGroup', 'Coordinator', 'AuthGroup', 'PreferredFirstName', 'StudName', 'StudEmail', 'Level', 'House', 'Id', 'ThirdParty_Code', 'ValidSASCode');
	private $teacherColumnList = array('StaffId', 'Password', 'Teacher', 'PreferredFirstName', 'Title', 'Email', 'Department', 'Phone', 'Location', 'StaffFirstName', 'StaffLastName', 'AuthGroup', 'House', 'Id', 'ThirdParty_Code', 'Salutation', 'StaffMobile');

	/**
	 * Process input
	 *
	 * @return void
	 */
	public function process() {

		/* Student account format
		 */
		if ($this->isMatchingColumns($this->getStudentColumnList())) {
			$this->processStudents();
		}
		/* Teacher account format
		 */
		else if ($this->isMatchingColumns($this->getTeacherColumnList())) {
			$this->processTeachers();
		}
		/* Unrecognized format
		 */
		else {
			die("User file is not in a recognised format.\n");
		}
	}

	/**
	 * Process student accounts
	 *
	 * @return void
	 */
	private function processStudents() {
		while ($propertyList = $this->getRow()) {
			$this->incrementLineCount();

			/* Check number of columns
			 */
			if ($this->isInvalidPropertyList($propertyList)) {
				continue;
			}

			/* Overwrite original var -_-
			 */
			$propertyList = $this->generatePropertyList($propertyList);

			/* Check id
			 */
			if ($this->isInvalidPropertyListId($propertyList, 'Id')) {
				continue;
			}

			$user = array(
				'synergy_id' => $propertyList['Id'],
				'username' => $propertyList['ThirdParty_Code'],
				'password' => $propertyList['Password'],
				'title' => '',
				'firstname' => $propertyList['StudFirstName'],
				'lastname' => $propertyList['StudLastName'],
				'year' => $propertyList['Level'],
				'house' => $propertyList['House'],
				'local_email' => 0,
				'alt_email' => $propertyList['StudEmail'],
				'email_pass' => $propertyList['Password'],
				'mail_handler' => 0,
				'su' => 0,
				'eportfolio_enabled' => 1,
				'group_id' => $this->getStudentGroupId($propertyList),
				'campus' => $this->getCampus(),
				'defaultCampus' => $this->getCampus(),
			);

			if ($userClean = $this->getUserHandler()->getBySynergy($user['synergy_id']) ) {
				$user['id'] = $userClean['uid'];

				$this->getUserHandler()->update($user);
			}
			else {
				$this->getUserHandler()->create($user);
			}
		}

		print("\n{$this->getErrorCount()} of {$this->getLineCount()} students failed to load.\n");
	}

	/**
	 * Process teacher accounts
	 *
	 * @return void
	 */
	private function processTeachers() {
		while ($propertyList = fgetcsv($this->getInputResource())) {
			$this->incrementLineCount();

			/* Check number of columns
			 */
			if ($this->isInvalidPropertyList($propertyList)) {
				continue;
			}

			/* Overwrite original var -_-
			 */
			$propertyList = $this->generatePropertyList($propertyList);

			/* Check id
			 */
			if ($this->isInvalidPropertyListId($propertyList, 'Id')) {
				continue;
			}

			$user = array(
				'synergy_id' => $propertyList['Id'],
				'username' => $propertyList['ThirdParty_Code'],
				'password' => $propertyList['Password'],
				'title' => $propertyList['Title'],
				'firstname' => $propertyList['StaffFirstName'],
				'lastname' => $propertyList['StaffLastName'],
				'year' => 0,
				'house' => $propertyList['House'],
				'local_email' => 0,
				'alt_email' => $propertyList['Email'],
				'email_pass' => $propertyList['Password'],
				'mail_handler' => 0,
				'su' => 0,
				'eportfolio_enabled' => 0,
				'group_id' => $this->getTeacherGroupId($propertyList),
				'campus' => $this->getCampus(),
				'defaultCampus' => $this->getCampus(),
			);

			if ($userClean = $this->getUserHandler()->getBySynergy($user['synergy_id'])) {
				$user['id'] = $userClean['uid'];

				$this->getUserHandler()->update($user);
			}
			else {
				$this->getUserHandler()->create($user);
			}
		}

		print("\n{$this->getErrorCount()} of {$this->getLineCount()} teachers failed to load.\n");
	}

	/**
	 * Get student group id
	 *
	 * @param  $propertyList
	 * @return string
	 */
	private function getStudentGroupId($propertyList) {
		return Database::getInstance()->query("
				SELECT id
				FROM groups
				WHERE name LIKE '{$propertyList['AuthGroup']}'
					AND group_type = '".Group::STUDENT_GROUP_TYPE."'
				LIMIT 1
			")->fetchColumn(0);
	}

	/**
	 * Get teacher group id
	 *
	 * @param  $propertyList
	 * @return string
	 */
	private function getTeacherGroupId($propertyList) {
		return Database::getInstance()->query("
				SELECT id
				FROM groups
				WHERE name LIKE '{$propertyList['AuthGroup']}'
					AND group_type = '".Group::STAFF_GROUP_TYPE."'
				LIMIT 1
			")->fetchColumn(0);
	}

	/**
	 * Get campus
	 *
	 * @return string
	 */
	private function getCampus() {
		return Database::getInstance()->query("SELECT id FROM luCampus LIMIT 1")->fetchColumn(0);
	}

	/* Setters/Getters
	 */

	/**
	 * Get student column list
	 *
	 * @return array
	 */
	private function getStudentColumnList() {
		return $this->studentColumnList;
	}

	/**
	 * Get teacher column list
	 *
	 * @return array
	 */
	private function getTeacherColumnList() {
		return $this->teacherColumnList;
	}

	/**
	 * Check if the updated propertyList id is valid
	 *
	 * @param  $data
	 * @param  $key
	 * @return bool
	 */
	private function isInvalidPropertyListId($data, $key) {
		if (!is_numeric($data[$key])) {
			print("Nonnumerical ID provided at line #{$this->getLineCount()} in {$this->getFileName()}\n");

			$this->incrementErrorCount();

			return true;
		}

		return false;
	}
}
