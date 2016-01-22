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
    private $parentColumnList = array('school_num', 'FirstName', 'Surname', 'SBUsername', 'SBPassword', 'email', 'Comment', 'CurrentParent');

	/* User Profile reference
	 */
	private $profile;

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
        /**
         * Parent account format
         */
        else if ($this->isMatchingColumns($this->getParentColumnList())) {
            $this->processParents();
        }
		/* Unrecognized format
		 */
		else {
			die("User file is not in a recognised format.{$this->getLineEnding()}");
		}

		echo "{$this->getLineEnding()}{$this->getLineEnding()}";
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

			$user = array(
				'synergy_id' => $propertyList['StudId'],
				'username' => $propertyList['ThirdParty_Code'],
				'title' => '',
				'firstname' => $propertyList['StudFirstName'],
				'lastname' => $propertyList['StudLastName'],
				'year' => $propertyList['Level'],
				'house' => $propertyList['House'],
				'local_email' => 0,
				'alt_email' => $propertyList['StudEmail'],
				'mail_handler' => 1,
				'su' => 0,
				'eportfolio_enabled' => 1,
				'group_id' => $this->getStudentGroupId($propertyList),
				'campus' => 1,
				'defaultCampus' => 1,
			);


			if ($userClean = $this->getUserHandler()->getBySynergy($user['synergy_id']) ) {
				$user['id'] = $userClean['uid'];
				if(!$this->getUserHandler()->update($user))
                    print "Unable to update user ".$propertyList['ThirdParty_Code'].'('.$propertyList['StudId'].')'.$user['id']."\n";
			}
			else {
				if(!$user['id'] = $this->getUserHandler()->create($user)) {
                    print "Unable to create user ".$propertyList['ThirdParty_Code'].'('.$propertyList['StudId'].')';
                }
			}

			/* Update user profile
			 */
			$this->setProfile(new Synchronise_External_Donvale_User_Profile());

			$this->getProfile()->setUserId($user['id']);
			$this->getProfile()->setDob($propertyList['DateofBirth']);
			$this->getProfile()->setLevel($propertyList['Level']);
			$this->getProfile()->setHomegroup($propertyList['HomeGroup']);
			$this->getProfile()->setHouse($propertyList['House']);

			$this->getProfile()->getGuardian1()->setName($propertyList['Guardian1']);
			$this->getProfile()->getGuardian1()->setHomePhone($propertyList['Guardian1Tel_H']);
			$this->getProfile()->getGuardian1()->setWorkPhone($propertyList['Guardian1Tel_W']);
			$this->getProfile()->getGuardian1()->setMobilePhone($propertyList['Guardian1Tel_M']);
			$this->getProfile()->getGuardian1()->setAddress($propertyList['Guardian1Add']);
			$this->getProfile()->getGuardian1()->setSuburb($propertyList['Guardian1Sub']);
			$this->getProfile()->getGuardian1()->setPostcode($propertyList['Guardian1PostC']);
			$this->getProfile()->getGuardian1()->setEmail($propertyList['Guardian1Email']);
			$this->getProfile()->getGuardian1()->setMailAddress($propertyList['Guardian1MailAdd']);

			$this->getProfile()->getGuardian2()->setName($propertyList['Guardian2']);
			$this->getProfile()->getGuardian2()->setHomePhone($propertyList['Guardian2Tel_H']);
			$this->getProfile()->getGuardian2()->setWorkPhone($propertyList['Guardian2Tel_W']);
			$this->getProfile()->getGuardian2()->setMobilePhone($propertyList['Guardian2Tel_M']);
			$this->getProfile()->getGuardian2()->setAddress($propertyList['Guardian2Add']);
			$this->getProfile()->getGuardian2()->setSuburb($propertyList['Guardian2Sub']);
			$this->getProfile()->getGuardian2()->setPostcode($propertyList['Guardian2PostC']);
			$this->getProfile()->getGuardian2()->setEmail($propertyList['Guardian2Email']);
			$this->getProfile()->getGuardian2()->setMailAddress($propertyList['Guardian2MailAdd']);

			$this->getProfile()->createUpdate();
		}

		print("{$this->getLineEnding()}{$this->getErrorCount()} of {$this->getLineCount()} students failed to load.{$this->getLineEnding()}");

		echo "{$this->getLineEnding()}{$this->getLineEnding()}";
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

			$user = array(
				'synergy_id' => $propertyList['StaffId'],
				'username' => $propertyList['ThirdParty_Code'],
				'title' => $propertyList['Title'],
				'firstname' => $propertyList['StaffFirstName'],
				'lastname' => $propertyList['StaffLastName'],
				'year' => '',
				'house' => $propertyList['House'],
				'local_email' => 0,
				'alt_email' => $propertyList['Email'],
				'mail_handler' => 1,
				'eportfolio_enabled' => 1,
				'group_id' => $this->getTeacherGroupId($propertyList),
				'campus' => 1,
				'defaultCampus' => 1,
			);

			if ($userClean = $this->getUserHandler()->getBySynergy($user['synergy_id'])) {
				$user['id'] = $userClean['uid'];

				$this->getUserHandler()->update($user);
			}
			else {
				$this->getUserHandler()->create($user);
			}
		}

		print("{$this->getLineEnding()}{$this->getErrorCount()} of {$this->getLineCount()} teachers failed to load.\n");
	}

    /**
   	 * Process parent accounts
   	 *
   	 * @return void
   	 */
   	private function processParents () {
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

            $user = array(
                'synergy_id' => $propertyList['SBUsername'],
                'username' => $propertyList['SBUsername'],
                'password' => $propertyList['SBPassword'],
                'title' => '',
                'firstname' => $propertyList['FirstName'],
                'lastname' => $propertyList['Surname'],
                'year' => 0,
                'house' => '',
                'local_email' => 0,
                'alt_email' => $propertyList['email'],
                'mail_handler' => 1,
                'su' => 0,
                'eportfolio_enabled' => 0,
                'group_id' => $this->getParentGroupId($propertyList),
                'campus' => 1,
                'defaultCampus' => 1,
            );

            if ($userClean = $this->getUserHandler()->getByUsername($user['username'])) {
                $user['id'] = $userClean['uid'];
                unset($user['password']); // dont update passwords
                $this->getUserHandler()->update($user);
            }
            else {
                $this->getUserHandler()->create($user);
            }
        }

        print("{$this->getLineEnding()}{$this->getErrorCount()} of {$this->getLineCount()} parents failed to load.\n");
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
            WHERE group_type = '".Group::STUDENT_GROUP_TYPE."'
            ORDER BY name LIKE '%{$propertyList['AuthGroup']}%' DESC
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
            WHERE group_type = '".Group::STAFF_GROUP_TYPE."'
            ORDER BY name LIKE '%{$propertyList['AuthGroup']}%' DESC
            LIMIT 1
        ")->fetchColumn(0);
   	}

    /**
   	 * Get parent group id
   	 *
   	 * @param  $propertyList
   	 * @return string
   	 */
   	private function getParentGroupId($propertyList) {
   		return Database::getInstance()->query("
            SELECT id
            FROM groups
            WHERE group_type = '".Group::PARENT_GROUP_TYPE."'
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
   	 * Get parent column list
   	 *
   	 * @return array
   	 */
   	private function getParentColumnList() {
   		return $this->parentColumnList;
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
			print("Nonnumerical ID provided at line #{$this->getLineCount()} in {$this->getFileName()}{$this->getLineEnding()}");

			$this->incrementErrorCount();

			return true;
		}

		return false;
	}

	/**
	 * Set profile
	 *
	 * @param $profile
	 */
	private function setProfile($profile) {
		$this->profile = $profile;
	}

	/**
	 * Get profile
	 *
	 * @return mixed
	 */
	private function getProfile() {
		return $this->profile;
	}


}
