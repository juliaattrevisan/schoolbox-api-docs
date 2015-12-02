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
	protected $db = null;

	/**
	 * A Handler of Groups
	 *
	 * @var
	 */
	protected $groupHandler;

	/**
	 * Helper
	 *
	 * @var External_Maze_Helper
	 */
	protected $helper = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setDb(MSSQL::getInstance());
		$this->setGroup(new Group());
		$this->setHelper(new External_Maze_Helper());
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
	protected function recombine($family) {

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
			$newFamily['Email'] = $family['SpouseDefaultEmail'];
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
					DF1.DFKEY AS RelatedID1,
					DF2.DFKEY AS RelatedID2,
					DF1.M_EMAIL AS FamilyEmail1,
					DF1.F_EMAIL AS FamilyEmail2,
					DF2.M_EMAIL AS AltFamilyEmail1,
					DF2.F_EMAIL AS AltFamilyEmail2
				FROM ST
				LEFT JOIN DF DF1 ON ST.FAMILY = DF1.DFKEY
				LEFT JOIN DF DF2 ON (ST.FAMB = DF2.DFKEY AND ST.LIVING_B != 'N')
				WHERE ST.STKEY IN $allIds
			";

			$allEmails = $this->getDb()->getAll($query);

			$processed = array();

			if (is_array($allEmails) && count($allEmails)) {
				foreach($allEmails as $currentEmail) {

                    /* Choose one of two possibilities
                     *
                     * How to choose for split parents?
                     *
                     * I have assumed that the record in FAMILY is the primary
                     * record to use, so decide which of the FAMILY emails to use
                     */
                    if (strlen($currentEmail['RelatedID1'])) {
                        if (strlen($currentEmail['FamilyEmail1'])) {
                            $processed[$currentEmail['RelatedID1']] = $currentEmail['FamilyEmail1'];
                        }
                        else if (strlen($currentEmail['FamilyEmail2'])) {
                            $processed[$currentEmail['RelatedID1']] = $currentEmail['FamilyEmail2'];
                        }
                    }

                    /* Can't tell whether a parent should be emailed at all,
                     * or ignored, in a split-family scenario
                     *
                     * Assume they will be emailed, split out the split parents
                     * into their own record in the processed stack
                     */
                    if (strlen($currentEmail['RelatedID2'])) {
                        if (strlen($currentEmail['AltFamilyEmail1'])) {
                            $processed[$currentEmail['RelatedID2']] = $currentEmail['AltFamilyEmail1'];
                        }
                        else if (strlen($currentEmail['AltFamilyEmail2'])) {
                            $processed[$currentEmail['RelatedID2']] = $currentEmail['AltFamilyEmail2'];
                        }
                    }
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
				'' AS StudentFormStaffName,
				'' AS TeacherFormID,
				'' AS FormDescription,
				ST.HOUSE AS StudentHouse,
				KGH.DESCRIPTION AS StudentHouseDescription,
				REPLACE(REPLACE(ST.SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS StudentYearLevel,
				'' AS StudentYearLevelCoordinator,
				'' AS YearLevelCoordID,
				'' AS StudentLockerBook,
				'' AS StudentEmergencyName,
				'' AS StudentEmergencyPhone,
				'' AS StudentEmergencyPhoneAlt,
				'' AS StudentEmergency2Name,
				'' AS StudentEmergency2Phone,
				'' AS StudentEmergency2PhoneAlt,
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
				'' AS StudentContactSuburb,
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
				'' AS StudentContactSuburb,
				'' AS StudentContactHomeSuburb,
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
        return $this->getHelper()->getCurrentSemester();
	}

    public function getCurrentYear($externalUserId = null)
    {
        return $this->getHelper()->getCurrentYear();
    }

	/**
	 * Get a list of subjects for a particular user
	 *
	 * @param $externalUserId
	 * @param $userId
	 * @return array|bool
	 */
	public function getSubjectList($externalUserId, $userId) {
        $allSubjects = array();

		if ($this->getGroup()->isStudent($userId)) {

			/* Get roll groups
			 */
			$query = "
				SELECT
					ST.FIRST_NAME,
					ST.SURNAME,
					KGC.ROLL_GROUP AS ClassCode,
					'' AS ClassNumber,
					'' AS classType,
					'' AS ClassCampus,
					KGC.DESCRIPTION AS name
				FROM ST
				LEFT JOIN KGC ON ST.ROLL_GROUP = KGC.ROLL_GROUP
				WHERE ST.STKEY = '{$externalUserId}'
			";

			$part1 = $this->getDb()->getAll($query);

			if (!$part1) {
				$part1 = array();
			}

			/* Get regular classes
			 */
			$query = "
				SELECT
					STMA.MKEY AS ClassCode,
					STMA.CLASS AS ClassNumber,
					SU.FULLNAME AS name,
					'' AS classType,
					'' AS ClassCampus
				FROM STMA
				LEFT JOIN SU ON STMA.MKEY = SU.SUKEY
				WHERE SKEY = '{$externalUserId}'
				AND STMA.TTPERIOD = '{$this->getHelper()->getCurrentTTPERIOD()}'
			";

			$part2 = $this->getDb()->getAll($query);

			if (!$part2) {
				$part2 = array();
			}

			$allSubjects = array_merge($part1, $part2);
		}
		else if ($this->getGroup()->isStaff($userId)) {
			$query = "
				SELECT
					KGC.ROLL_GROUP AS ClassCode,
					'' AS ClassNumber,
					'' AS classType,
					'' AS ClassCampus,
					KGC.DESCRIPTION AS name
				FROM KGC
				WHERE TEACHER = '{$externalUserId}'
			";

			$part1 = $this->getDb()->getAll($query);

			if (!$part1) {
				$part1 = array();
			}

			$query = "
				SELECT
					THTQ.SUBJ AS ClassCode,
					THTQ.CLASS AS ClassNumber,
					SU.FULLNAME AS name,
					'' AS classType,
					'' AS ClassCampus
				FROM THTQ
				INNER JOIN SU ON SU.SUKEY = THTQ.SUBJ
				WHERE THTQ.QKEY = '{$this->getHelper()->getCurrentTHKEY()}'
				AND (THTQ.T1TEACH = '{$externalUserId}' OR THTQ.T2TEACH = '{$externalUserId}')
			";

			$part2 = $this->getDb()->getAll($query);

			if (!$part2) {
				$part2 = array();
			}

			$allSubjects = array_merge($part1, $part2);
		}

		/* Return structure
		 */
		$subjectList = array();

		$processedIds = array();

		/* Format it in a schoolbox-friendly way
		 * Also uniqueify this list using classCode/classNumber combination
		 */
		if (is_array($allSubjects) && count($allSubjects)) {
			foreach($allSubjects as $currentSubject) {

				/* Don't display these timetable items as part of Subject List
				 * (Not Available, Faculty Meetings, Gate Duty, etc)
				 */
				$match = preg_match('#^NOT|^FACUL|^MEET|^TSHOP|^FRONT|^STAFF|\d?A/F|^QUAD#', $currentSubject['ClassCode']);

				if (!$match) {
					if (strlen($currentSubject['ClassNumber'])) {
						$classCode = $this->getHelper()->generateClassCode($currentSubject['ClassCode'], $currentSubject['ClassNumber']);
					}
					else {
						$classCode = $currentSubject['ClassCode'];
					}

					if (!in_array($classCode, $processedIds)) {
						$subjectList[] = array(
							'code' => $classCode,
							'name' => $currentSubject['name'],
							'classType' => $currentSubject['classType'],
							'campusCode' => $currentSubject['ClassCampus']
						);

						$processedIds[] = $classCode;
					}
				}
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
		$query = "
			SELECT ST.STKEY AS RelatedID
			FROM ST
			WHERE FAMILY = '{$synergyID}'
			OR FAMB = '{$synergyID}'
		";

		return $this->getDb()->getAll($query);
	}

	/**
	 * Get an email address for a particular person
	 *
	 * @param $externalUserId
	 * @return bool
	 */
	public function getEmail($externalUserId) {

		/* TODO: This doesn't seem to be used in Schoolbox?
		 */
	}

	/**
	 * Returns a local path to the user's portrait
	 *
	 * @param Integer $externalUserId
	 * @return Bool|String
	 */
	public function getPortrait($externalUserId) {
		$userMapper = new \Schoolbox\UserMapper();
        try {
		    $user = $userMapper->findOneByColumn('synergy_id', $externalUserId);
        } catch (Exception_Schoolbox $e) {
            /* User not found */
            return false;
        }

		/* Students and staff store their pics in different places
		 */
		if ($this->getGroup()->isStudent($user->getId())) {
			$query = "
				SELECT
					CONVERT(VARCHAR, LW_DATE, 120) AS ModifiedDate
				FROM ST
				WHERE ST.STKEY = '{$externalUserId}'
			";
		}
		else if ($this->getGroup()->isStaff($user->getId())) {
			$query = "
				SELECT
					CONVERT(VARCHAR, LW_DATE, 120) AS ModifiedDate
				FROM SF
				WHERE SF.SFKEY = '{$externalUserId}'
			";
		}

		if ($photo = $this->getDb()->getRow($query)) {
			$photoPath = Config::get('portrait_dir') . '/' . $externalUserId . '.jpg';

			//TODO: delete photos on rollover

			/* If the cached file doesn't exist, or the synergetic image is more recent than our
			 * last known cached version, regenerate cached version.
			 *
			 * Separate the queries out, fetching the photo data only if it's newer than the cached
			 * version, or if the cache version doesn't already exist.
			 */
			$cacheTime = @filemtime($photoPath);
			$modifiedTime = strtotime($photo['ModifiedDate']);

			if (!file_exists($photoPath) || ($cacheTime < $modifiedTime)) {
				if ($this->getGroup()->isStudent($user->getId())) {
					$query = "
						SELECT
							STUDENT_PIC AS Photo
						FROM ST
						WHERE ST.STKEY = '{$externalUserId}'
					";
				}
				else if ($this->getGroup()->isStaff($user->getId())) {
					$query = "
						SELECT
							STAFF_PIC AS Photo
						FROM SF
						WHERE SF.SFKEY = '{$externalUserId}'
					";
				}

				$photo = $this->getDb()->getRow($query);

				/* Purge all previously transformed copies
				 */
				$pattern = Config::get('stream_dir') . "/portrait/" . $externalUserId . '-*.dat';

				foreach (glob($pattern) as $oldCacheFile) {
					unlink($oldCacheFile);
				}

				/* Save the image
				 */
				$photoHandle = fopen($photoPath, "w");
				fwrite($photoHandle, $photo['Photo']);
				fclose($photoHandle);
			}

			return $photoPath;
		}

		return false;
	}

	/**
	 * Used in sync scripts
	 */
	public function getStudentUserAccountInfo() {
		$query = "
			SELECT
				USER_NAME AS NetworkLogin,
				NULL AS StudentTitle,
				FIRST_NAME AS StudentPreferred,
				SURNAME AS StudentSurname,
				STKEY AS StudentID,
				NULL AS StudentOccupEmail,
				REPLACE(REPLACE(ST.SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS StudentYearLevel,
				HOUSE AS StudentHouse,
				BOARDER AS boarding_status,
				CAMPUS AS campus
			FROM ST
			WHERE STATUS != 'LEFT'
		";

		return $this->getDb()->getAll($query);
	}

	/**
	 * Used in sync scripts
	 */
	public function getParentUserAccountInfo() {
		$query = "
			SELECT
				DF.DFKEY AS DFKEY,
				DF.DFKEY AS ID,
				DF.FTITLE AS Title,
				DF.FNAME AS Preferred,
				DF.FSURNAME AS Surname,
				DF.F_EMAIL AS Email,
				(DF.DFKEY + '1') AS SpouseID,
				DF.MTITLE AS SpouseTitle,
				DF.MNAME AS SpousePreferred,
				DF.MSURNAME AS SpouseSurname,
				DF.M_EMAIL AS SpouseDefaultEmail,
				ST.SCHOOL_YEAR
			FROM ST
			LEFT JOIN DF ON (ST.FAMILY = DF.DFKEY OR ST.FAMB = DF.DFKEY)
		";

		$allFamilies = $this->getDb()->getAll($query);

		$families = array();

		foreach($allFamilies as $currentFamily) {
			$newFamily = $this->recombine($currentFamily);
			$newFamily['ID'] = $currentFamily['ID'];

			/* Change the key names with "StudentContact" as a prefix
			 * due to different interfaces from the original scenario
			 * recombine() was built for
			 */
			$allKeys = array_keys($newFamily);

			$updatedFamily = array();

			foreach($allKeys as $currentKey) {
				if ($currentKey == 'ContactID') {
					$key = 'Student' . $currentKey;
					$updatedFamily[$key] = $currentFamily[$currentKey];
				}
				else {
					$key = 'StudentContact' . $currentKey;
					$updatedFamily[$key] = $newFamily[$currentKey];
				}
			}

			$families[$currentFamily['DFKEY']] = $updatedFamily;
		}

		$data = array_values($families);

		return $data;
	}

	/**
	 * Used in sync scripts
	 *
	 * Staff members with a Finish date are ignored
	 * Staff members with staff_type L (left/departed) are ignored
	 */
	public function getStaffUserAccountInfo() {
		$query = "
			SELECT
				SFKEY AS StaffID,
				USER_NAME AS NetworkLogin,
				STAFF_TYPE AS ActiveFlag,
				NULL AS StaffTitle,
				FIRST_NAME AS StaffPreferredName,
				SURNAME AS StaffSurname,
				NULL AS DefaultEmailCode,
				NULL AS OccupEmail,
				SF_EMAIL AS Email,
				CAMPUS AS StaffCampus,
				NULL AS StaffCategoryType,
				NULL AS StaffCategory,
				NULL AS StaffDepartment
			FROM SF
			WHERE FINISH IS NULL
			AND STAFF_TYPE != 'L'
		";

		return $this->getDb()->getAll($query);
	}

    /**
     * List of campuses a staff member is involved in based on timetable
     *
     * @param int $staffID
     * @return array|bool
     */
    public function getCampusFromTimetable($staffID)
    {
        return array();
    }


    /**
	 * Determine what kind of boarder this person is
	 */
	public function determineBoarderType($boarderValue) {
		if ($boarderValue == 'Y') {
			return 'Boarder';
		}

		return 'Day';
	}

	/**
	 * Get contact list for staff directory
	 *
	 * @return array
	 */
	public function getStaffContactListExtended() {
		// TODO: Implement getStaffContactListExtended() method.
		return array();
	}

	/**
	 * Stub method, probably not used
	 */
	public function getHousemasterByHouseId($houseID) {
	}

    /**
     * Not yet implemented for this connector
     *
     * @return array
     */
    public function getStudentForms()
    {
        return [];
    }

	/* Getters/Setters
	 */

	/**
	 * Set db
	 *
	 * @param null $db
	 */
	protected function setDb($db) {
		$this->db = $db;
	}

	/**
	 * Get db
	 *
	 * @return null
	 */
	protected function getDb() {
		return $this->db;
	}

	/**
	 * Set group
	 *
	 * @param Group $groupHandler
	 */
	protected function setGroup($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * Get group
	 *
	 * @return Group
	 */
	protected function getGroup() {
		return $this->groupHandler;
	}

	/**
	 * Set helper
	 *
	 * @param External_Maze_Helper $helper
	 */
	protected function setHelper($helper) {
		$this->helper = $helper;
	}

	/**
	 * Get helper
	 *
	 * @return External_Maze_Helper
	 */
	protected function getHelper() {
		return $this->helper;
	}
}
