<?php
/**
 * External_Maze_Loreto_User
 *
 * Loreto-specific customisations of Maze
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
*/
declare(strict_types=1);

class External_Maze_Loreto_User extends External_Maze_User {

	/**
	 * @inheritDoc
	 */
    public function getStudent(string $externalUserId): array
    {

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
			    '' AS StudentGender,
				SUBSTRING(CONVERT(VARCHAR, ST.BIRTHDATE, 120), 0, 11) AS StudentBirthDate,
				(ADVISOR.FIRST_NAME + ' ' + ADVISOR.SURNAME) AS StudentTutorName,
				KGC.DESCRIPTION AS StudentTutorDescription,
				'' AS TeacherTutorID,
				HEADOFHOUSE.SFKEY AS HeadOfHouseID,
				(HEADOFHOUSE.FIRST_NAME + ' ' + HEADOFHOUSE.SURNAME) AS StudentHeadOfHouse,
				'' AS StudentForm,
				'' AS StudentFormStaffName, -- This field is hydrated below
				'' AS TeacherFormID,
				ST.HOUSE AS StudentHouse,
				KGH.DESCRIPTION AS StudentHouseDescription,
				REPLACE(REPLACE(ST.SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS StudentYearLevel,
				(COORDINATOR.FIRST_NAME + ' ' + COORDINATOR.SURNAME) AS StudentYearLevelCoordinator,
				COORDINATOR.SFKEY AS YearLevelCoordID,
				(COACH.FIRST_NAME + ' ' + COACH.SURNAME) AS StudentCoach,
				COACH.SFKEY AS CoachID,
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
				'' AS StudentMobilePhone,
				'' AS StudentStudiesCode,
				'' AS StudentSpecialNeedsFlag,
				'' AS EnglishSecondFlag,
				'' AS AlertMessage,
                '' AS CourtOrderType,
				'' AS StudentBoarder,
				'' AS Comment,
                '' AS StudentEnrolmentDate,
                '' AS StudentLeavingDate,
				'' AS StudentIBFlag,
				ST.ROLL_GROUP,
				0 AS countIndividualEducationPlans
			FROM ST
			LEFT JOIN KGH ON ST.HOUSE = KGH.HOUSE
			LEFT JOIN KGC ON ST.ROLL_GROUP = KGC.ROLL_GROUP
			LEFT JOIN KCT ON ST.SCHOOL_YEAR = KCT.KCTKEY
			LEFT JOIN SF ADVISOR ON KCT.SFLINK = ADVISOR.SFKEY
			LEFT JOIN KCY ON ST.SCHOOL_YEAR = KCY.KCYKEY
			LEFT JOIN KCP ON ST.PCG = KCP.KCPKEY
			LEFT JOIN SF COORDINATOR ON KCY.YEAR_COORD = COORDINATOR.SFKEY
			LEFT JOIN SF HEADOFHOUSE ON KGH.SFLINK = HEADOFHOUSE.SFKEY
			LEFT JOIN SF COACH ON KCP.SFLINK = COACH.SFKEY
			WHERE STKEY = '{$externalUserId}'
		";

		$student = $this->getDb()->getRow($query);

		/* Assign birthday
		 */
		$birthday = new \Alaress_Date_Format($student['StudentBirthDate'], 'Y-m-d');
		$student['StudentBirthDate'] = $birthday->getUnixTimeStamp();

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

			$student['StudentFormStaffName'] = implode(', ', $processed);
		}

		return $student;
	}

	/**
	 * @inheritDoc
	 */
    public function getStudentContactList(array $externalUserId): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        $ids = implode(',', array_map(static function($val ){
                return sprintf("'%s'", $val);
            }, $externalUserId)
        );

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
				'' AS StudentContactSpouseRelation,
				CASE WHEN DF.DFKEY = ST.FAMILY THEN 1 ELSE 0 END as primaryFamily
			FROM DF
			LEFT JOIN UM ON DF.HOMEKEY = UM.UMKEY
			LEFT JOIN ST ON (DF.DFKEY = ST.FAMILY OR (ST.FAMB = DF.DFKEY AND ST.LIVING_B != 'N'))
			WHERE ST.STKEY IN ($ids)
			ORDER BY primaryFamily DESC
		";

		return $this->getDb()->getAll($query);
	}

	/**
	 * @inheritDoc
	 */
    public function getStudentContactListExtended(string $yearLevel, string $name, string $boardingStatus, string $form): array
    {

		/* Mega select
		 */
		$query = "
			SELECT
				ST.STKEY AS StudentID,
				ST.FIRST_NAME AS StudentPreferred,
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
		if (strlen($yearLevel)) {

			/* Translate back to Maze-specific
			 */
			if (is_numeric($yearLevel)) {
				$mazeYearLevel = $yearLevel;

				if ($yearLevel < 10) {
					$mazeYearLevel = '0' . $yearLevel;
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

            $splitNames = explode(' ', $name);

            foreach ($splitNames as $splitName) {
                $splitName = $this->getDb()->quote($splitName);
                $query .= "
                    AND (
                        (ST.SURNAME LIKE '%{$splitName}%')
                        OR
                        (ST.FIRST_NAME LIKE '%{$splitName}%')
                    )
                ";
            }
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
