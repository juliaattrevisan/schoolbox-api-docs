<?php
/**
 * External_SchoolPro_User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_SchoolPro_User implements External_UserInterface {

	/**
	 * An instance of MSSQL
	 *
	 * @var null
	 */
	private $db = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setDatabase(MSSQL::getInstance());
	}

	/**
	 * Gets the contact info of the person who is a Student's Contact. The ID
	 * passed is the Synergetic of the Contact, not the Student.
	 *
	 * @param $externalId
	 */
	public function getParent($externalId) {
		$vars = array(
			'sbGetParent',
			array(
				array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pSchoolNum', $externalId, SQLINT4, false, false, -1),
			)
		);

		$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		new \Alaress_Probe($result);

		if ($result['success']) {
		}
		else {
		}
	}

	/**
	 * Gets the contact info of the person who is a Student's Contact. The ID
	 * passed is the Synergetic of the Contact, not the Student. Anyone who is
	 * not supposed to be having their details publicised (via the
	 * DirectoryIncludeFlag) are not included in the result set.
	 * Accept array of Synergy IDs, or just an int
	 *
	 * @param $externalId
	 *
	 * @return mixed
	 */
	public function getParentSync($externalId) {
		$vars = array(
			'sbGetParent',
			array(
				array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pSchoolNum', $externalId, SQLINT4, false, false, -1),
			)
		);

		$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		new \Alaress_Probe($result);

		if ($result['success']) {
		}
		else {
		}
	}

	/**
	 * TODO: Not specified in a stored proc
	 *
	 * Get all the parents emails for a class, or group of students
	 *
	 * @param array $synergyIds
	 *
	 * @return array
	 */
	public function getParentEmails($synergyIds) {
		return array();
	}

	/**
	 * Get a guy's info from SchoolPro
	 *
	 * @param  $synId
	 * @return array|bool
	 */
	public function getStudent($synId) {
		$vars = array(
			'sbGetStudent',
			array(
				array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pSchoolNum', $synId, SQLINT4, false, false, -1),
			)
		);

		$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		if ($result['success'] && count($result['result'])) {
			$currentStudent = $result['result'][0];

			$student = array(
				'StudentID' => $currentStudent['ID'],
				'StudentGiven1' => $currentStudent['Firstname'],
				'StudentSurname' => $currentStudent['Surname'],
				'StudentBirthDate' => $currentStudent['Birthdate'],
				'StudentHouse' => $currentStudent['HeadOfHouse'],
				'StudentYearLevel' => $currentStudent['YearLevel'],
				'StudentTutor' => $currentStudent['TutorName'],
				'StudentForm' => $currentStudent['FormName'],
				'StudentFormStaffName' => $currentStudent['FormStaffName'],
				'StudentEmergencyName' => $currentStudent['EmergencyContactName'],
				'StudentEmergencyPhone' => $currentStudent['EmergencyContactPhone'],
			);

			return $student;
		}

		return false;
	}

	/**
	 * TODO: Not specified in a stored proc
	 *
	 * Get a guy's info from SchoolPro
	 *
	 * @param  $synId
	 * @return array|bool
	 */
	public function getStaff($synId) {
	}

	/**
	 * TODO: Not specified in a stored proc
	 *
	 * Get a list of student contacts (parents) and their contact information
	 *
	 * @param $externalUserId
	 * @return mixed
	 */
	public function getStudentContactList($externalUserId) {
	}

	/**
	 * TODO: Not specified in a stored proc
	 *
	 * Gets a list of Contacts for all Students in a particular year level. The
	 * year level is stored in Synergetic.
	 *
	 * Used in reporting.
	 *
	 * Note that there is no StudentContactHomeCountryDesc in the Synergetic views
	 * so I changed it to use CountryCode instead.
	 *
	 * @param $yearlevel
	 * @param $name
	 * @param $boardingStatus
	 * @param $form
	 *
	 * @return mixed
	 */
	public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form) {
	}

	/**
	 * TODO: Not specified in a stored proc
	 *
	 * @param null $externalUserId
	 */
	public function getCurrentSemester($externalUserId = null) {
	}

	/**
	 * Get a list of subjects for a particular user
	 *
	 * @param $externalUserId
	 * @param $userId
	 * @return array|bool
	 */
	public function getSubjectList($externalUserId, $userId) {
		$groupHandler = new Group();

		if ($groupHandler->isStudent($userId)) {
			$vars = array(
				'sbGetStudentSubjectList',
				array(
					array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
					array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
					array('@pSchoolNum', $externalUserId, SQLINT4, false, false, -1),
				)
			);

			$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

			if ($result['success'] && count($result['result'])) {
				$subjectList = array();

				foreach($result['result'] as $currentSubject) {
					$subject = array(
						'code' => $currentSubject['ClassID'],
						'name' => $currentSubject['ItemName']
					);

					$subjectList[] = $subject;
				}

				return $subjectList;
			}
		}
		/* Otherwise assume staff
		 */
		else {
			$vars = array(
				'sbGetStaffSubjectList',
				array(
					array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
					array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
					array('@pSchoolNum', $externalUserId, SQLINT4, false, false, -1),
				)
			);

			$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

			if ($result['success'] && count($result['result'])) {
				$subjectList = array();

				/* TODO: Make stored proc consistent with the StudentSubjectList one
				 * Have to confer with Scoolpro to do that.
				 */
				foreach($result['result'] as $currentSubject) {
					$subject = array(
						'code' => $currentSubject['ID'],
						'name' => $currentSubject['ItemName']
					);

					$subjectList[] = $subject;
				}

				return $subjectList;
			}
		}

		return false;
	}

	/**
	 * Get a list of enrolled students
	 * (What is a RelationSchoolNum?)
	 *
	 * @param $synergyID
	 */
	public function getEnrolledStudents($synergyID) {
		$vars = array(
			'sbGetEnrolledStudents',
			array(
				array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pSchoolNum', $synergyID, SQLINT4, false, false, -1),
			)
		);

		$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		new \Alaress_Probe($result);

		if ($result['success']) {
		}
		else {
		}
	}

	/**
	 * Get an email address for a particular person
	 *
	 * @param $externalUserId
	 */
	public function getEmail($externalUserId) {
		$userMapper = new Schoolbox\UserMapper();
		$user = $userMapper->findByColumn('synergyId', $externalUserId);

		/* Use a different query depending on the type of person
		 */
		if ($user->isStudent()) {
			$result = $this->getStudent($externalUserId);

			return $result['email'];
		}
		else if ($user->isParent()) {
			$result = $this->getParent($externalUserId);

			return $result['email'];
		}
		else if ($user->isStaff()) {

			/* TODO: Not supplied?
			 */
		}
	}

	/**
	 * Returns a local path to the user's portrait
	 *
	 * This indicates that Cedar needs to upload the pics manually to portrait_dir
	 *
	 * @param Integer $externalUserId
	 * @return Bool|String
	 */
	public function getPortrait($externalUserId) {
		$portraitPath = realpath(Config::get('portrait_dir') ) . '/' .strtolower($externalUserId) . '.jpg';

		if (is_readable($portraitPath)) {
			return $portraitPath;
		}

		return false;
	}

	/**
	 * Stub method, probably not used
	 */
	public function getStudentUserAccountInfo() {
	}

	/**
	 * Stub method, probably not used
	 */
	public function getParentUserAccountInfo() {
	}

	/**
	 * Stub method, probably not used
	 */
	public function getStaffUserAccountInfo() {
	}

	/**
	 * Stub method, probably not used
	 */
	public function determineBoarderType($boarderValue) {
	}

	/**
	 * Stub method, probably not used
	 */
	public function getHousemasterByHouseId($houseID) {
	}

	/* Getters/Setters
	 */

	/**
	 * Set db
	 *
	 * @param null $db
	 */
	private function setDb($db) {
		$this->db = $db;
	}

	/**
	 * Get db
	 *
	 * @return null
	 */
	private function getDb() {
		return $this->db;
	}
}