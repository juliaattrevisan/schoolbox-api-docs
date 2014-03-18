<?php
/**
 * External_Maze_User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Maze_User implements External_UserInterface {

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
		$this->setDb(MSSQL::getInstance());
	}

	/**
	 * An index of all the tablaeus
	 */
	public function tableIndex() {
		$query = "
			SELECT Distinct TABLE_NAME
			FROM information_schema.TABLES
		";

		new \Alaress_Probe($this->getDb()->getAll($query));

		$query = "
			SELECT DB_NAME() AS DataBaseName
		";

		new \Alaress_Probe($this->getDb()->getAll($query));
	}

	/**
	 * Gets the contact info of the person who is a Student's Contact. The ID
	 * passed is the Synergetic of the Contact, not the Student.
	 *
	 * In Maze, Parents aren't considered atomic units of people, so always
	 * refer to them in a joint account.
	 *
	 * SynergyId is DFKEY
	 *
	 * @param $externalId
	 * @return array|bool
	 */
	public function getParent($externalId) {
		$query = "
			SELECT
				DFKEY AS ContactID,
				MTITLE AS ContactTitle,
				MNAME AS Preferred,
				MSURNAME AS Surname,
				M_EMAIL AS Email,
				'' AS HomePhone,
				MMOBILE AS MobilePhone,
				'' AS SilentPhoneFlag,
				'' AS DirectoryIncludeFlag,
				DFKEY AS ContactSpouseID,
				FTITLE AS SpouseTitle,
				FNAME AS SpousePreferred,
				FSURNAME AS SpouseSurname,
				F_EMAIL AS SpouseEmail,
				'' AS SpouseHomePhone,
				FMOBILE AS SpouseMobilePhone,
				'' AS SpouseDirectoryIncludeFlag
			FROM DF
			WHERE DFKEY = '{$externalId}'
		";

		return $this->getDb()->getRow($query);
	}

	/**
	 * 1 + 1 = 1
	 *
	 * @param $family
	 */
	private function recombine($family) {

		/* Combine everything into a joint account
		 *
		 * Try to accommodate single-parent families listed as either the
		 * mother or the father
		 */
		$newFamily = array();
		$newFamily['ContactID'] = $family['ContactID'];

		if (strlen($family['Preferred']) && strlen($family['SpousePreferred'])) {
			$newFamily['Title'] = "{$family['Title']} & {$family['SpouseTitle']}";
			$newFamily['Preferred'] = "{$family['Preferred']} & {$family['SpousePreferred']}";

			/* Decide whose surname to use
			 */
			$newFamily['Surname'] = $family['Surname'];

			/* Decide whose email to use
			 */
			if (strlen($family['Email'])) {
				$newFamily['Email'] = $family['Email'];
			}
			else if (strlen($family['SpouseEmail'])) {
				$newFamily['Email'] = $family['SpouseEmail'];
			}

			/* Decide whose mobile to use
			 */
			if (strlen($family['MobilePhone'])) {
				$newFamily['MobilePhone'] = $family['MobilePhone'];
			}
			else if (strlen($family['SpouseMobilePhone'])) {
				$newFamily['SpouseMobilePhone'] = $family['SpouseMobilePhone'];
			}

			/* Decide whose home phone to use
			 */
			$newFamily['HomePhone'] = $family['HomePhone'];
		}
		else if (strlen($family['Preferred'])) {
			$newFamily['Title'] = $family['Title'];
			$newFamily['Preferred'] = $family['Preferred'];
			$newFamily['Surname'] = $family['Surname'];
			$newFamily['Email'] = $family['Email'];
			$newFamily['HomePhone'] = $family['HomePhone'];
			$newFamily['MobilePhone'] = $family['MobilePhone'];
		}
		else if (strlen($family['SpousePreferred'])) {
			$newFamily['Title'] = $family['SpouseTitle'];
			$newFamily['Preferred'] = $family['SpousePreferred'];
			$newFamily['Surname'] = $family['SpouseSurname'];
			$newFamily['Email'] = $family['SpouseEmail'];
			$newFamily['HomePhone'] = $family['SpouseHomePhone'];
			$newFamily['MobilePhone'] = $family['SpouseMobilePhone'];
		}

		return $newFamily;
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
		if (is_array($externalId)) {
			$assignation = ' IN (\'' . implode('\',\'', $externalId) . '\')';
		}
		else {
			$assignation = " = '$externalId'";
		}

		$query = "
			SELECT
				DFKEY AS ContactID,
				MTITLE AS Title,
				MNAME AS Preferred,
				MSURNAME AS Surname,
				M_EMAIL AS Email,
				DFKEY AS SpouseID,
				FTITLE AS SpouseTitle,
				FNAME AS SpousePreferred,
				FSURNAME AS SpouseSurname,
				F_EMAIL AS SpouseEmail,
				'' AS OccupEmail,
				'' AS DefaultEmailCode,
				'' AS DirectoryIncludeFlag
			FROM DF
			WHERE DFKEY $assignation
		";

		if (is_array($externalId)) {
			$families = $this->getDb()->getAll($query);

			$allFamilies = array();

			foreach($families as $currentFamily) {
				$processed = $this->recombine($currentFamily);
				$processed['ID'] = $currentFamily['ContactID'];

				$allFamilies[] = $processed;
			}
		}
		else {
			$currentFamily = $this->getDb()->getRow($query);

			$processed = $this->recombine($currentFamily);
			$processed['ID'] = $currentFamily['ContactID'];

			$allFamilies = $this->recombine($processed);

		}

		return $allFamilies;
	}

	/**
	 * Get all the parents emails for a class, or group of students
	 *
	 * @param array $synergyIds
	 *
	 * @return array
	 */
	public function getParentEmails($synergyIds) {
		if (!count($synergyIds)) {
			return array();
		}
		else {
			$allIds = '(\'' . implode('\',\'', $synergyIds) . '\')';

			$query = "
				SELECT
					DFKEY AS RelatedID,
					M_EMAIL AS Email1,
					F_EMAIL AS Email2
				FROM ST
				LEFT JOIN DF ON ST.FAMILY = DF.DFKEY
				WHERE ST.STKEY IN $allIds
			";

			$allEmails = $this->getDb()->getAll($query);

			$processed = array();

			if (is_array($allEmails) && count($allEmails)) {
				foreach($allEmails as $currentEmail) {

					/* Choose one of two possibilities
					 */
					if (strlen($currentEmail['Email1'])) {
						$newEmail = array(
							'RelatedID' => $currentEmail['Email1']
						);
					}
					else if (strlen($currentEmail['Email2'])) {
						$newEmail = array(
							'RelatedID' => $currentEmail['Email2']
						);
					}

					$processed[] = $newEmail;
				}
			}

			return $processed;
		}
	}

	/**
	 * Get a guy's info from Schoolpro
	 *
	 * $synId is the STKEY value.
	 *
	 * @param  $synId
	 * @return array|bool
	 */
	public function getStudent($synId) {

		/* The School Year is never represented as just an int, it's always a
		 * string of some sort like "Y04" or "Year 2" or "Students In Year 3"
		 * so you have to replace chars
		 *
		 * Student Tutor, Name and Description are Initials, Formal Name and
		 * Full Name respectively.
		 *
		 * Student Head Of House is also their full name.
		 *
		 * StudentForm is an identifier; StudentFormStaffName is their full name.
		 */
		$query = "
			SELECT
				STKEY AS StudentID,
				FIRST_NAME AS StudentGiven1,
				SURNAME AS StudentSurname,
				CONVERT(VARCHAR, BIRTHDATE, 120) AS StudentBirthDate,
				'' AS StudentTutor,
				'' AS StudentTutorName,
				'' AS StudentTutorDescription,
				'' AS TeacherTutorID,
				'' AS HeadOfHouseID,
				'' AS StudentHeadOfHouse,
				'' AS StudentForm,
				'' AS StudentFormStaffName,
				'' AS TeacherFormID,
				'' AS FormDescription,
				ST.HOUSE AS StudentHouse,
				KGH.DESCRIPTION AS StudentHouseDescription,
				REPLACE(REPLACE(SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS StudentYearLevel,
				'' AS StudentYearLevelCoordinator,
				'' AS YearLevelCoordID,
				'' AS StudentLockerBook,
				EMERGENCY_ONE_NAME AS StudentEmergencyName,
				EMERGENCY_ONE_PHONE AS StudentEmergencyPhone,
				EMERGENCY_ONE_MOBILE AS StudentEmergencyPhoneAlt,
				EMERGENCY_TWO_NAME AS StudentEmergency2Name,
				EMERGENCY_TWO_PHONE AS StudentEmergency2Phone,
				EMERGENCY_TWO_MOBILE AS StudentEmergency2PhoneAlt,
				'' AS StudentEmergency3Name,
				'' AS StudentEmergency3Phone,
				'' AS StudentEmergency3PhoneAlt,
				MEDICAL_ALERT AS StudentMedicalAlert,
				'' AS StudentSpecialNeedsFlag,
				'' AS AlertMessage,
				'' AS StudentBoarder
			FROM ST
			LEFT JOIN KGH ON ST.HOUSE = KGH.HOUSE
			WHERE KLPID = '{$synId}'
		";

		return $this->getDb()->getAll($query);
	}

	/**
	 * Get a guy's info from Schoolpro
	 *
	 * @param  $synId
	 * @return array|bool
	 */
	public function getStaff($synId) {
	}

	/**
	 * Get a list of student contacts (parents) and their contact information
	 *
	 * @param $externalUserId
	 * @return mixed
	 */
	public function getStudentContactList($externalUserId) {
	}

	/**
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
	}

	/**
	 * Returns a list of externalIds
	 *
	 * @param $synergyID
	 * @return array|bool
	 */
	public function getEnrolledStudents($synergyID) {
	}

	/**
	 * Get an email address for a particular person
	 *
	 * @param $externalUserId
	 * @return bool
	 */
	public function getEmail($externalUserId) {
	}

	/**
	 * Returns a local path to the user's portrait
	 *
	 * @param Integer $externalUserId
	 * @return Bool|String
	 */
	public function getPortrait($externalUserId) {
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