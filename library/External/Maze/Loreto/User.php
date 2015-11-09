<?php
/**
 * External_Maze_Loreto_User
 *
 * Loreto-specific customisations of Maze
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Maze_Loreto_User extends External_Maze_User {

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
				ST.STKEY AS StudentID,
				ST.FIRST_NAME AS StudentGiven1,
				ST.SURNAME AS StudentSurname,
				SUBSTRING(CONVERT(VARCHAR, ST.BIRTHDATE, 120), 0, 11) AS StudentBirthDate,
				'' AS StudentTutor,
				(ADVISOR.FIRST_NAME + ' ' + ADVISOR.SURNAME) AS StudentTutorName,
				KGC.DESCRIPTION AS StudentTutorDescription,
				'' AS TeacherTutorID,
				'' AS HeadOfHouseID,
				'' AS StudentHeadOfHouse,
				'' AS StudentForm,
				(COORDINATOR.FIRST_NAME + ' ' + COORDINATOR.SURNAME) AS StudentFormStaffName,
				'' AS TeacherFormID,
				'' AS FormDescription,
				ST.HOUSE AS StudentHouse,
				KGH.DESCRIPTION AS StudentHouseDescription,
				REPLACE(REPLACE(ST.SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS StudentYearLevel,
				'' AS StudentYearLevelCoordinator,
				'' AS YearLevelCoordID,
				'' AS StudentLockerBook,
				ST.EMERGENCY_ONE_NAME AS StudentEmergencyName,
				ST.EMERGENCY_ONE_PHONE AS StudentEmergencyPhone,
				ST.EMERGENCY_ONE_MOBILE AS StudentEmergencyPhoneAlt,
				ST.EMERGENCY_TWO_NAME AS StudentEmergency2Name,
				ST.EMERGENCY_TWO_PHONE AS StudentEmergency2Phone,
				ST.EMERGENCY_TWO_MOBILE AS StudentEmergency2PhoneAlt,
				'' AS StudentEmergency3Name,
				'' AS StudentEmergency3Phone,
				'' AS StudentEmergency3PhoneAlt,
				ST.MEDICAL AS StudentMedicalAlert,
				'' AS StudentStudiesCode,
				'' AS StudentSpecialNeedsFlag,
				'' AS AlertMessage,
				'' AS StudentBoarder,
				ST.ROLL_GROUP
			FROM ST
			LEFT JOIN KGH ON ST.HOUSE = KGH.HOUSE
			LEFT JOIN KGC ON ST.ROLL_GROUP = KGC.ROLL_GROUP
			LEFT JOIN KCT ON ST.SCHOOL_YEAR = KCT.KCTKEY
			LEFT JOIN SF ADVISOR ON KCT.SFLINK = ADVISOR.SFKEY
			LEFT JOIN KCY ON ST.SCHOOL_YEAR = KCY.KCYKEY
			LEFT JOIN SF COORDINATOR ON KCY.YEAR_COORD = COORDINATOR.SFKEY
			WHERE STKEY = '{$synId}'
		";

		$student = $this->getDb()->getRow($query);

		/* Assign birthday
		 */
		$birthday = new \Alaress_Date_Format($student['StudentBirthDate'], 'Y-m-d');
		$student['StudentBirthDate'] = $birthday->getUnixTimestamp();

		/* Find one or more homeroom teachers and condense them
		 */
		$query = "
			SELECT
				SF.FIRST_NAME AS FIRSTNAME,
				SF.SURNAME
			FROM KGC
			LEFT JOIN SF ON KGC.TEACHER = SF.SFKEY
                        OR KGC.TEACHERB = SF.SFKEY
			WHERE KGC.ROLL_GROUP = '{$student['ROLL_GROUP']}'
		";

		$allHomeroomTeachers = $this->getDb()->getAll($query);

		if (is_array($allHomeroomTeachers) && count($allHomeroomTeachers)) {
			$processed = array();

			foreach ($allHomeroomTeachers as $currentHomeroomTeacher) {
				$processed[] = $currentHomeroomTeacher['FIRSTNAME'] . ' ' . $currentHomeroomTeacher['SURNAME'];
			}

			/* Uniques only
			 */
			$processed = array_values(array_unique($processed));

			$student['StudentHeadOfHouse'] = implode(', ', $processed);
		}

		return $student;
	}

	/**
	 * Get a list of student contacts (parents) and their contact information
	 *
	 * @param $externalUserId
	 * @return mixed
	 */
	public function getStudentContactList($externalUserId) {
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
				UM.SUBURB AS StudentContactSuburb,
				POSTCODE AS StudentContactPostCode,
				STATE AS StudentContactState,
				TELEPHONE AS StudentContactPhoneActual,
				MMOBILE AS StudentContactMobilePhone,
				MBUS_PHONE AS StudentContactOccupPhone,
				'' AS StudentContactRelation,
				DFKEY AS StudentContactSpouseID,
				FTITLE AS StudentContactSpouseTitle,
				FNAME AS StudentContactSpousePreferred,
				FSURNAME AS StudentContactSpouseSurname,
				F_EMAIL AS StudentContactSpouseDefaultEmail,
				TELEPHONE AS StudentContactSpousePhoneActual,
				FMOBILE AS StudentContactSpouseMobilePhone,
				FBUS_PHONE AS StudentContactSpouseOccupPhone,
				'' AS StudentContactSpouseRelation
			FROM DF
			LEFT JOIN UM ON DF.HOMEKEY = UM.UMKEY
			LEFT JOIN ST ON (DF.DFKEY = ST.FAMILY OR (ST.FAMB = DF.DFKEY AND ST.LIVING_B != 'N'))
			WHERE ST.STKEY = '{$externalUserId}'
		";

	        return $this->getDb()->getAll($query);
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
     	 * Note that if a student has split parents, they will appear more than once.
     	 * They will appear once for each split parent.
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
				UM.SUBURB AS StudentContactHomeSuburb,
				UM.POSTCODE AS StudentContactPostCode,
				UM.STATE AS StudentContactState,
				'' AS StudentContactCountryCode,
				UM.ADDRESS01 AS StudentContactHomeAddress1,
				UM.ADDRESS02 AS StudentContactHomeAddress2,
				UM.ADDRESS03 AS StudentContactHomeAddress3,
				UM.POSTCODE AS StudentContactHomePostCode,
				UM.STATE AS StudentContactHomeState,
				'' AS StudentContactHomeCountryCode,
				'' AS StudentContactType,
				UM.SILENT_NUMBER AS StudentContactSilentPhoneFlag,
				'' AS PrimaryDirectoryIncludeFlag,
				'' AS SpouseDirectoryIncludeFlag,
				'' AS StudentParentsSeparatedFlag
			FROM ST
			LEFT JOIN DF ON (ST.FAMILY = DF.DFKEY OR (ST.FAMB = DF.DFKEY AND ST.LIVING_B != 'N'))
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
}
