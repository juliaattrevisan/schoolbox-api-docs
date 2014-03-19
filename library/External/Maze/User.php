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
	 * A Handler of Groups
	 *
	 * @var
	 */
	private $groupHandler;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setDb(MSSQL::getInstance());
		$this->setGroup(new Group());
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
	 * passed is the ExternalId of the Contact, not the Student.
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
	 * @return array
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
	 * passed is the ExternalId of the Contact, not the Student. Anyone who is
	 * not supposed to be having their details publicised (via the
	 * DirectoryIncludeFlag) are not included in the result set.
	 * Accept array of ExternalIDs, or just an int
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
							$currentEmail['RelatedID'] => $currentEmail['Email1']
						);
					}
					else if (strlen($currentEmail['Email2'])) {
						$newEmail = array(
							$currentEmail['RelatedID'] => $currentEmail['Email2']
						);
					}

					$processed[] = $newEmail;
				}
			}

			return $processed;
		}
	}

	/**
	 * Get a guy's info from Maze
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
	 * Get a guy's info from Maze
	 *
	 * None of the users seem to have any value for CAMPUS
	 *
	 * @param  $synId
	 * @return array|bool
	 */
	public function getStaff($synId) {
		$query = "
			SELECT
				SFKEY AS SchoolStaffCode,
				'' AS StaffBuilding,
				'' AS StaffRoom,
				'' AS StaffExtension,
				MOBILE AS StaffMobilePhone,
				'' AS StaffVoiceMail,
				'' AS StaffCampusDescription,
				SF_POSITION AS StaffCategoryDescription,
				'' AS StaffDepartment
			FROM SF
			LEFT JOIN KSC ON SF.POS_CODE = KSC.KSCKEY
			WHERE SFKEY = '{$synId}'
		";

		return $this->getDb()->getRow($query);
	}

	/**
	 * Get a list of student contacts (parents) and their contact information
	 *
	 * @param $externalUserId
	 * @return mixed
	 */
	public function getStudentContactList($externalUserId) {

		/* First find the student so you know the Family identifier
		 */
		$query = "
			SELECT FAMILY
			FROM ST
			WHERE STKEY = '{$externalUserId}'
		";

		$student = $this->getDb()->getRow($query);

		/* Get the family's data
		 */
		$query = "
			SELECT
				DFKEY AS StudentContactID,
				MTITLE AS StudentContactTitle,
				MNAME AS StudentContactPreferred,
				MSURNAME AS StudentContactSurname,
				M_EMAIL AS StudentContactDefaultEmail,
				ADDRESS01 AS StudentContactAddress1,
				ADDRESS02 AS StudentContactAddress2,
				ADDRESS03 AS StudentContactAddress3,
				SUBURB AS StudentContactSuburb,
				POSTCODE AS StudentContactPostCode,
				STATE AS StudentContactState,
				'' AS StudentContactPhoneActual,
				MMOBILE AS StudentContactMobilePhone,
				MBUS_PHONE AS StudentContactOccupPhone,
				'' AS StudentContactRelation,
				DFKEY AS StudentContactSpouseID,
				FTITLE AS StudentContactSpouseTitle,
				FNAME AS StudentContactSpousePreferred,
				FSURNAME AS StudentContactSpouseSurname,
				F_EMAIL AS StudentContactSpouseDefaultEmail,
				'' AS StudentContactSpousePhoneActual,
				FMOBILE AS StudentContactSpouseMobilePhone,
				FBUS_PHONE AS StudentContactSpouseOccupPhone,
				'' AS StudentContactSpouseRelation
			FROM DF
			LEFT JOIN UM ON DF.HOMEKEY = UM.UMKEY
			WHERE DFKEY = '{$student['FAMILY']}'
		";

		return $this->getDb()->getRow($query);
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

		/* Mega select
		 */
		$query = "
			SELECT
				ST.STKEY AS StudentID,
				ST.FIRST_NAME AS StudentGiven1,
				ST.SURNAME AS StudentSurname,
				REPLACE(REPLACE(ST.SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS StudentYearLevel,
				ST.HOUSE AS StudentHouse,
				ST.BOARDER AS StudentBoarder,
				KGC.DESCRIPTION AS StudentForm,

				DF.DFKEY AS StudentContactID,
				DF.MTITLE AS StudentContactTitle,
				DF.MNAME AS StudentContactPreferred,
				'' AS StudentContactLegalFullName,
				DF.MSURNAME AS StudentContactSurname,
				DF.M_EMAIL AS StudentContactDefaultEmail,
				'' AS StudentContactPhoneActual,
				DF.MMOBILE AS StudentContactMobilePhone,
				DF.MBUS_PHONE AS StudentContactOccupPhone,

				DF.DFKEY AS StudentContactSpouseID,
				DF.MTITLE AS StudentContactSpouseTitle,
				DF.MNAME AS StudentContactSpousePreferred,
				'' AS StudentContactSpouseLegalFullName,
				DF.MSURNAME AS StudentContactSpouseSurname,
				DF.M_EMAIL AS StudentContactSpouseDefaultEmail,
				'' AS StudentContactPhoneActual,
				DF.MMOBILE AS StudentContactSpouseMobilePhone,
				DF.MBUS_PHONE AS StudentContactSpouseOccupPhone,

				UM.ADDRESS01 AS StudentContactAddress1,
				UM.ADDRESS02 AS StudentContactAddress2,
				UM.ADDRESS03 AS StudentContactAddress3,
				UM.SUBURB AS StudentContactSuburb,
				UM.POSTCODE AS StudentContactPostCode,
				UM.STATE AS StudentContactState,
				'' AS StudentContactCountryCode,
				UM.ADDRESS01 AS StudentContactHomeAddress1,
				UM.ADDRESS02 AS StudentContactHomeAddress2,
				UM.ADDRESS03 AS StudentContactHomeAddress3,
				UM.SUBURB AS StudentContactHomeSuburb,
				UM.POSTCODE AS StudentContactHomePostCode,
				UM.STATE AS StudentContactHomeState,
				'' AS StudentContactHomeCountryCode,

				'' AS StudentContactType,
				UM.SILENT_NUMBER AS StudentContactSilentPhoneFlag,
				'' AS PrimaryDirectoryIncludeFlag,
				'' AS SpouseDirectoryIncludeFlag,
				'' AS StudentParentsSeparatedFlag
			FROM ST
			LEFT JOIN DF ON ST.FAMILY = DF.DFKEY
			LEFT JOIN UM ON DF.HOMEKEY = UM.UMKEY
			LEFT JOIN KGC ON ST.ROLL_GROUP = KGC.ROLL_GROUP
			WHERE 1 = 1
		";

		/* Search for a particular year level
		 */
		if (strlen($yearlevel)) {

			/* Translate back to Maze-specific
			 */
			if (is_numeric($yearlevel)) {
				$mazeYearLevel = $yearlevel;

				if ($yearlevel < 10) {
					$mazeYearLevel = '0' . $yearlevel;
				}

				$mazeYearLevel = 'Y' . $mazeYearLevel;

				$query .= "
					AND (ST.SCHOOL_YEAR = '{$mazeYearLevel}')
				";
			}
		}

		/* Search for a student name
		 */
		if (strlen($name)) {
			$name = $this->getDb()->quote($name);
			$query .= "
				AND (
					(ST.SURNAME LIKE '%{$name}%')
					OR
					(ST.FIRST_NAME LIKE '%{$name}%')
				)
			";
		}

		/* Search for a particular boarding status
		 */
		if ($boardingStatus == '') {

			/* No restrictions on result set
			 */
		}
		else if ($boardingStatus == 'yes') {
			$query .= "
				AND ST.BOARDER = 'Y'
			";
		}
		else {
			$query .= "
				AND ST.BOARDER != 'Y'
			";
		}

		/* Search for a particular form
		 */
		if ($form) {
			$form = $this->getDb()->quote($form);

			$query .= "
				AND KGC.DESCRIPTION = '{$form}'
			";
		}

		$query .= "
			ORDER BY StudentSurname, StudentID, StudentContactType
		";

		return $this->getDb()->getAll($query);
	}

	/**
	 * Gets a list of Contacts for all Student Boarders.
	 *
	 * Used in reporting.
	 *
	 * @return mixed
	 */
	public function getStudentBoarderContactListExtended() {
		return $this->getStudentContactListExtended('', '', 'Y', '');
	}

	/**
	 * Get the current semester
	 *
	 * This should take the form below:
	 * $row['CURRENT_SEMESTER'] = '1998S4';
	 *
	 * @param null $externalUserId
	 */
	public function getCurrentSemester($externalUserId = null) {
		$query = "
			SELECT CURRENT_SEMESTER
			FROM SI
		";

		$row = $this->getDb()->getRow($query);

		preg_match('/\d+S(\d+)/', $row['CURRENT_SEMESTER'], $matches);

		return $matches[1];
	}

	/**
	 * Get a list of subjects for a particular user
	 *
	 * @param $externalUserId
	 * @param $userId
	 * @return array|bool
	 */
	public function getSubjectList($externalUserId, $userId) {
		$now = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

		$currentYear = $now->getUsingFormat('Y');
		$currentSemester = $this->getCurrentSemester();

		/* TODO: Hard code this for now
		 *
		 * CURRENT_SEMESTER is empty in the Maze db
		 */
		$currentSemester = 1;

		/* Only get subjects that are relevant now
		 */
		$ttPeriod = "{$currentYear}S{$currentSemester}";

		$externalUserId = 'ABRANAT';

		if ($this->getGroup()->isStudent($userId)) {
			$query = "
				SELECT
					STMA.MKEY AS ClassCode,
					SU.FULLNAME AS name,
					'' AS classType,
					'' AS ClassCampus
				FROM STMA
				LEFT JOIN SU ON STMA.MKEY = SU.SUKEY
				WHERE SKEY = '{$externalUserId}'
				AND TTPERIOD = '{$ttPeriod}'
			";
		}
		else if ($this->getGroup()->isStaff($userId)) {
			$query = "
				SELECT
					SUBJ AS ClassCode,
					SU.FULLNAME AS name,
					'' AS classType,
					'' AS ClassCampus
				FROM TTTG
				LEFT JOIN SF ON TTTG.T1TEACH = SF.SFKEY
				LEFT JOIN SU ON TTTG.SUBJ = SU.SUKEY
				WHERE SF.SFKEY = '{$externalUserId}'
			";
		}
		
		$allSubjects = $this->getDb()->getAll($query);

		$subjectList = array();
		$processed = array();

		/* Uniqueify results
		 */
		foreach($allSubjects as $currentSubject) {
			if (!in_array($currentSubject['ClassCode'], $processed)) {
				$subjectList[] = array(
					'code' => $currentSubject['ClassCode'],
					'name' => $currentSubject['name'],
					'classType' => $currentSubject['classType'],
					'campusCode' => $currentSubject['ClassCampus']
				);

				$processed[] = $currentSubject['ClassCode'];
			}
		}

		return $subjectList;
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

	/**
	 * Set group
	 *
	 * @param Group $groupHandler
	 */
	private function setGroup($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * Get group
	 *
	 * @return Group
	 */
	private function getGroup() {
		return $this->groupHandler;
	}
}