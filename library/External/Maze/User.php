<?php
/**
 * External_Maze_User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Resource\Storage;
use Schoolbox\User;

class External_Maze_User implements External_UserInterface
{

    protected const REPLACE_CHARACTERS_FROM_YEAR_LEVEL = "REPLACE(REPLACE(ST.SCHOOL_YEAR, 'Y0', ''), 'Y', '')";

    /**
     * An instance of MSSQL
     *
     * @var MSSQL $db
     */
    protected $db;

    /**
     * A Handler of Groups
     *
     * @var Group $groupHandler
     */
    protected $groupHandler;

    /**
     * Helper
     *
     * @var External_Maze_Helper $helper
     */
    protected $helper;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDb(MSSQL::getInstance());
        $this->setGroup(new Group());
        $this->setHelper(new External_Maze_Helper());
    }

    /**
     * In Maze, Parents aren't considered atomic units of people, so always
     * refer to them in a joint account.
     *
     * $externalId is DFKEY
     *
     * @inheritDoc
     */
    public function getParent(string $externalUserId): array
    {
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
			WHERE DFKEY = '{$externalUserId}'
		";

        return $this->getDb()->getRow($query) ?: [];
    }

    /**
     * 1 + 1 = 1
     *
     * @param array $family
     * @return array
     */
    private function recombine(array $family): array
    {

        /* Combine everything into a joint account
         *
         * Try to accommodate single-parent families listed as either the
         * mother or the father
         */
        $newFamily = array();
        $newFamily['ContactID'] = $family['ContactID'];

        // We need to know if these contain any real data. ie not NULL, nor empty, nor just whitespace
        $preferred         = trim($family['Preferred'] ?? '');
        $spousePreferred   = trim($family['SpousePreferred'] ?? '');
        $email             = trim($family['Email'] ?? '');
        $spouseEmail       = trim($family['SpouseEmail'] ?? '');
        $mobilePhone       = trim($family['MobilePhone'] ?? '');
        $spouseMobilePhone = trim($family['SpouseMobilePhone'] ?? '');

        if ($preferred !== '' && $spousePreferred !== '') {
            $newFamily['Title'] = "{$family['Title']} & {$family['SpouseTitle']}";
            $newFamily['Preferred'] = "{$family['Preferred']} & {$family['SpousePreferred']}";

            /* Decide whose surname to use
             */
            $newFamily['Surname'] = $family['Surname'];

            /* Decide whose email to use
             */
            if ($email !== '') {
                $newFamily['Email'] = $family['Email'];
            } elseif ($spouseEmail !== '') {
                $newFamily['Email'] = $family['SpouseEmail'];
            }

            /* Decide whose mobile to use
             */
            if ($mobilePhone !== '') {
                $newFamily['MobilePhone'] = $family['MobilePhone'];
            }
            elseif ($spouseMobilePhone !== '') {
                $newFamily['SpouseMobilePhone'] = $family['SpouseMobilePhone'];
            }

            /* Decide whose home phone to use
             */
            $newFamily['HomePhone'] = $family['HomePhone'];
        }
        elseif ($preferred !== '') {
            $newFamily['Title']       = $family['Title'];
            $newFamily['Preferred']   = $family['Preferred'];
            $newFamily['Surname']     = $family['Surname'];
            $newFamily['Email']       = $family['Email'];
            $newFamily['HomePhone']   = $family['HomePhone'];
            $newFamily['MobilePhone'] = $family['MobilePhone'];
        }
        elseif ($spousePreferred !== '') {
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
     * @inheritDoc
     */
    public function getParentEmails(array $externalUserIds): array
    {
        if (!count($externalUserIds)) {
            return array();
        }
        $allIds = '(\'' . implode('\',\'', $externalUserIds) . '\')';

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
            foreach ($allEmails as $currentEmail) {

                // Testing that column is not NULL, empty, or just whitespace
                $relatedID1      = trim($currentEmail['RelatedID1'] ?? '');
                $relatedID2      = trim($currentEmail['RelatedID2'] ?? '');
                $familyEmail1    = trim($currentEmail['FamilyEmail1'] ?? '');
                $familyEmail2    = trim($currentEmail['FamilyEmail2'] ?? '');
                $altFamilyEmail1 = trim($currentEmail['AltFamilyEmail1'] ?? '');
                $altFamilyEmail2 = trim($currentEmail['AltFamilyEmail2'] ?? '');

                /*
                 * Need to get both FamilyEmail1 and FamilyEmail2, as one is for father
                 * and the other is for mother in some schools.
                 */
                if ($relatedID1 !== '') {
                    if ($familyEmail1) {
                        $processed[$currentEmail['RelatedID1']] = $currentEmail['FamilyEmail1'];
                    }
                    if ($familyEmail2 !== '') {
                        // As the second email shares the same DFKEY as the first email, appending extra to the key.
                        $processed["{$currentEmail['RelatedID1']}-2"] = $currentEmail['FamilyEmail2'];
                    }
                }

                /* Can't tell whether a parent should be emailed at all,
                 * or ignored, in a split-family scenario
                 *
                 * Assume they will be emailed, split out the split parents
                 * into their own record in the processed stack
                 */
                if ($relatedID2 !== '') {
                    if ($altFamilyEmail1 !== '') {
                        $processed[$currentEmail['RelatedID2']] = $currentEmail['AltFamilyEmail1'];
                    }
                    if ($altFamilyEmail2 !== '') {
                        // As the second email shares the same DFKEY as the first email, appending extra to the key.
                        $processed["{$currentEmail['RelatedID2']}-2"] = $currentEmail['AltFamilyEmail2'];
                    }
                }
            }
        }

        return $processed;
    }

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
        $query = sprintf("
			SELECT
				ST.STKEY AS StudentID,
				ST.FIRST_NAME AS StudentGiven1,
				ST.SURNAME AS StudentSurname,
			    '' AS StudentGender,
				SUBSTRING(CONVERT(VARCHAR, ST.BIRTHDATE, 120), 0, 11) AS StudentBirthDate,
				(ADVISOR.FIRST_NAME + ' ' + ADVISOR.SURNAME) AS StudentTutorName,
				KGC.DESCRIPTION AS StudentTutorDescription,
				'' AS TeacherTutorID,
				'' AS HeadOfHouseID,
				'' AS StudentHeadOfHouse,
				'' AS StudentForm,
				'' AS StudentFormStaffName,
				'' AS TeacherFormID,
				ST.HOUSE AS StudentHouse,
				KGH.DESCRIPTION AS StudentHouseDescription,
				%s AS StudentYearLevel,
				'' AS StudentYearLevelCoordinator,
				'' AS YearLevelCoordID,
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
			WHERE STKEY = '{$externalUserId}'
		", self::REPLACE_CHARACTERS_FROM_YEAR_LEVEL);

        $student = $this->getDb()->getRow($query) ?: [];

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
     * @inheritDoc
     */
    public function getStaff(string $externalUserId): array
    {
        $query = "
			SELECT
				SFKEY AS SchoolStaffCode,
				'' AS StaffBuilding,
				'' AS StaffRoom,
				'' AS StaffExtension,
				MOBILE AS StaffMobilePhone,
				MOBILE AS StaffDefaultMobilePhone,
				'' AS StaffVoiceMail,
				'' AS StaffCampusDescription,
				SF_POSITION AS StaffCategoryDescription,
				'' AS StaffDepartment,
				'' AS StaffHouseDescription
			FROM SF
			LEFT JOIN KSC ON SF.POS_CODE = KSC.KSCKEY
			WHERE SFKEY = '{$externalUserId}'
		";

        return $this->getDb()->getRow($query) ?: [];
    }

    /**
     * @inheritDoc
     */
    public function getStudentContactList(array $externalUserId): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        $ids = implode(',', array_map(static function ($val) {
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
				TELEPHONE AS StudentContactPhoneActual,
				UM.SILENT_NUMBER AS StudentContactSilentPhoneFlag,
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
        $query = sprintf("
			SELECT
				ST.STKEY AS StudentID,
				ST.FIRST_NAME AS StudentPreferred,
				ST.SURNAME AS StudentSurname,
				%s AS StudentYearLevel,
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
		", self::REPLACE_CHARACTERS_FROM_YEAR_LEVEL);

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
        } elseif ($boardingStatus == 'yes') {
            $query .= "
                AND ST.BOARDER = 'Y'
            ";
        } else {
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
    public function getStudentBoarderContactListExtended()
    {
        return $this->getStudentContactListExtended('', '', 'Y', '');
    }

    /**
     * Get the current semester
     *
     * This should take the form below:
     * $row['CURRENT_SEMESTER'] = '1998S4';
     *
     * @inheritDoc
     */
    public function getCurrentSemester(string $externalUserId = null): ?string
    {
        return $this->getHelper()->getCurrentSemester();
    }

    /**
     * @inheritDoc
     */
    public function getCurrentYear(string $externalUserId = null): ?string
    {
        return $this->getHelper()->getCurrentYear();
    }

    /**
     * @inheritDoc
     */
    public function getSubjectList(User $user): array
    {
        $externalId = $user->getExternalId();

        if (empty($externalId)) {
            return [];
        }

        if ($user->getRole()->isStudent()) {

            /* Get roll groups
             */
            $query = "
				SELECT
					ST.FIRST_NAME,
					ST.SURNAME,
					KGC.ROLL_GROUP AS ClassCode,
					NULL AS ClassNumber,
					'' AS classType,
					'' AS ClassCampus,
					KGC.DESCRIPTION AS name
				FROM ST
				LEFT JOIN KGC ON ST.ROLL_GROUP = KGC.ROLL_GROUP
				WHERE ST.STKEY = '{$externalId}'
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
				WHERE SKEY = '{$externalId}'
				AND STMA.TTPERIOD = '{$this->getHelper()->getCurrentTTPERIOD()}'
			";

            $part2 = $this->getDb()->getAll($query);

            if (!$part2) {
                $part2 = array();
            }

            $allSubjects = array_merge($part1, $part2);
        }
        elseif ($user->getRole()->isStaff()) {
            $query = "
                SELECT
                    KGC.ROLL_GROUP AS ClassCode,
                    NULL AS ClassNumber,
                    '' AS classType,
                    '' AS ClassCampus,
                    KGC.DESCRIPTION AS name
                FROM KGC
                WHERE TEACHER = '{$externalId}'
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
                AND (THTQ.T1TEACH = '{$externalId}' OR THTQ.T2TEACH = '{$externalId}')
            ";

            $part2 = $this->getDb()->getAll($query);

            if (!$part2) {
                $part2 = array();
            }

            $allSubjects = array_merge($part1, $part2);
        }
        else {
            return [];
        }

        /* Return structure
         */
        $subjectList = array();

        $processedIds = array();

        /* Format it in a schoolbox-friendly way
         * Also uniqueify this list using classCode/classNumber combination
         */
        if (is_array($allSubjects) && count($allSubjects)) {
            foreach ($allSubjects as $currentSubject) {

                /* Don't display these timetable items as part of Subject List
                 * (Not Available, Faculty Meetings, Gate Duty, etc)
                 */
                if (!$currentSubject['ClassCode']) {
                    continue;
                }
                $match = preg_match('#^NOT|^FACUL|^MEET|^TSHOP|^FRONT|^STAFF|\d?A/F|^QUAD#', $currentSubject['ClassCode']);

                if (!$match) {
                    if ($currentSubject['ClassNumber'] !== null) {
                        $classCode = $this->getHelper()->generateClassCode($currentSubject['ClassCode'], $currentSubject['ClassNumber']);
                    } else {
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
     * @inheritDoc
     */
    public function getEnrolledStudents(string $externalUserId): array
    {
        $query = "
			SELECT ST.STKEY AS RelatedID
			FROM ST
			WHERE FAMILY = '{$externalUserId}'
			OR FAMB = '{$externalUserId}'
		";

        return $this->getDb()->getAll($query);
    }


    /**
     * Returns a local path to the user's portrait
     *
     * @param User $user
     * @return Storage\Model|null
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getPortrait(User $user): ?Storage\Model
    {

        $externalId = $this->getDb()->smartQuote($user->getExternalId());

        /* Students and staff store their pics in different places
         */
        if ($this->getGroup()->isStudent($user->getId())) {
            $query = "
                SELECT
                    CONVERT(VARCHAR, LW_DATE, 120) AS ModifiedDate
                FROM ST
                WHERE ST.STKEY = {$externalId}
            ";
        } elseif ($this->getGroup()->isStaff($user->getId())) {
            $query = "
                SELECT
                    CONVERT(VARCHAR, LW_DATE, 120) AS ModifiedDate
                FROM SF
                WHERE SF.SFKEY = {$externalId}
            ";
        } else {

            /* Unknown user roletype: can't definitively fetch picture
             * I guess they get what we last knew
             */
            return $user->getPortrait();
        }

        if ($photo = $this->getDb()->getRow($query)) {

            // TODO: delete photos on rollover

            /* If this $user doesn't have an image, or if the SIS picture is more recent than the one we
             * currently have, shank it down and save it into brownhole
             *
             * (the latter occurs when the sis gets updated with a new picture and we need to update our storage)
             */
            if ((null === $user->getPortrait()) || $user->getPortrait()->getCreatedAt()->lt(\Alaress_Date_Format::newFromTimestamp(strtotime($photo['ModifiedDate'])))) {
                $queryPhoto = false;

                if ($this->getGroup()->isStudent($user->getId())) {
                    $queryPhoto = "
                        SELECT
                            STUDENT_PIC AS Photo
                        FROM ST
                        WHERE ST.STKEY = {$externalId}
                    ";
                } elseif ($this->getGroup()->isStaff($user->getId())) {
                    $queryPhoto = "
                        SELECT
                            STAFF_PIC AS Photo
                        FROM SF
                        WHERE SF.SFKEY = {$externalId}
                    ";
                }

                if ($queryPhoto) {
                    $photo = $this->getDb()->getRow($queryPhoto)['Photo'] ?? null;

                    if (!$photo) {
                        return null;
                    }

                    /* Save it into brownhole
                     */
                    $portrait = Storage\Mapper::getInstance()->acceptStream($user->getExternalId() . '.jpg', $photo);

                    /* Update user with latest version
                     */
                    $user->setPortrait($portrait);

                    \Schoolbox\UserMapper::getInstance()->save($user);
                }
            }

            /* Then return the latest Storage\Model instance
             */
            return $user->getPortrait();
        }

        // There was no photo in the SIS, so we don't want to display the cached image either- delete it from
        // disk so it doesn't magically appear later after they'd specifically deleted it

        /* Unlink from user
         */
        $user->setPortrait(null);

        \Schoolbox\UserMapper::getInstance()->save($user);

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getCampusFromTimetable(string $staffID): array
    {
        return array();
    }

    /**
     * @inheritDoc
     */
    public function determineBoarderType(?string $boarderValue): string
    {
        if ($boarderValue === 'Y') {
            return 'Boarder';
        }

        return 'Day';
    }

    /**
     * Get contact list for staff directory
     *
     * @return array
     */
    public function getStaffContactListExtended(): array
    {
        // TODO: Implement getStaffContactListExtended() method.
        return array();
    }

    /**
     * @inheritdoc
     */
    public function getHousemasterEmails(array $externalUserIds): array
    {
        return [];
    }

    /**
     * Not yet implemented for this connector
     *
     * @return array
     */
    public function getStudentForms(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function getAllYearLevels(): array
    {
        $query = sprintf("
            SELECT DISTINCT %s AS Code, %s AS Description
            FROM ST
            WHERE ISNULL(ST.SCHOOL_YEAR, '') != ''
        ", self::REPLACE_CHARACTERS_FROM_YEAR_LEVEL, self::REPLACE_CHARACTERS_FROM_YEAR_LEVEL);
        return $this->getDb()->getAll($query);
    }

    /* Getters/Setters
     */

    /**
     * Set db
     *
     * @param MSSQL $db
     */
    protected function setDb($db)
    {
        $this->db = $db;
    }

    /**
     * Get db
     *
     * @return MSSQL
     */
    protected function getDb()
    {
        return $this->db;
    }

    /**
     * Set group
     *
     * @param Group $groupHandler
     */
    protected function setGroup($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * Get group
     *
     * @return Group
     */
    protected function getGroup()
    {
        return $this->groupHandler;
    }

    /**
     * Set helper
     *
     * @param External_Maze_Helper $helper
     */
    protected function setHelper($helper)
    {
        $this->helper = $helper;
    }

    /**
     * Get helper
     *
     * @return External_Maze_Helper
     */
    protected function getHelper()
    {
        return $this->helper;
    }
}
