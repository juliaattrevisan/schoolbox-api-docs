<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * HumanEdge implementation of user retrieve methods.
 */
class External_Humanedge_User implements External_UserInterface
{
    /**
     * @var MSSQL $database
     */
    private $database;

    /**
     * A database connection for the A+ database (if required).
     *
     * @var MSSQL
     */
    private $aPlusDatabase;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
    }

    /**
     * @inheritDoc
     */
    public function getStudent($externalUserId)
    {
        // Smoosh all of the contacts into one
        $query = "
            SELECT
                s.Code AS StudentID,
                s.FirstName AS StudentGiven1,
                s.LastName AS StudentSurname,
                s.DOB AS StudentBirthDate,
                '' AS StudentHouseDescription,
                s.House AS StudentHouse,
                '' AS HeadOfHouseID,
                '' AS StudentHeadOfHouse,
                s.BoarderType AS StudentBoarder,
                s.Form AS StudentForm,
                '' AS FormDescription,
                '' AS StudentFormStaffName,
                '' AS StudentTutor,
                '' AS StudentTutorName,
                '' AS StudentTutorDescription,
                '' AS TeacherFormID,
                '' AS TeacherTutorID,
                s.Year AS StudentYearLevel,
                '' AS YearLevelCoordID,
                '' AS StudentYearLevelCoordinator,
                '' AS StudentLockerBook,
                (c.FirstName + ' ' + c.LastName) AS StudentEmergencyName,
                c.PhoneHome AS StudentEmergencyPhone,
                c.PhoneWork AS StudentEmergencyPhoneAlt,
                '' AS StudentMedicalAlert,
                s.SpecialNeeds AS StudentSpecialNeedsFlag,
                '' AS StudentStudiesCode,
                '' AS AlertMessage
            FROM Student s
            LEFT JOIN StudentContact sc ON s.ID = sc.StudentID AND sc.EmergencyContact = 'Y'
            LEFT JOIN Contact c ON sc.ContactID = c.ID
            WHERE s.Code = {$this->getDatabase()->smartQuote($externalUserId)}
        ";
        $result = [];
        $i = 1;
        foreach ($this->getDatabase()->getAll($query) as $row) {
            if (empty($result)) {
                $result = $row;
            } else {
                $result["StudentEmergency{$i}Name"]     = $row['StudentEmergencyName'];
                $result["StudentEmergency{$i}PhoneAlt"] = $row['StudentEmergencyPhoneAlt'];
                $result["StudentEmergency{$i}Phone"]    = $row['StudentEmergencyPhone'];
            }
            $i++;
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getStudentContactList($externalUserId)
    {
        $query = "
            SELECT
                c.Code AS StudentContactID,
                c.Title AS StudentContactTitle,
                c.FirstName AS StudentContactPreferred,
                c.LastName AS StudentContactSurname,
                c.Email AS StudentContactDefaultEmail,
                '' AS ValidAddressFlag,
                ca.Address1 AS StudentContactAddress1,
                ca.Address2 AS StudentContactAddress2,
                ca.Address3 AS StudentContactAddress3,
                '' AS StudentContactSuburb,
                ca.PostCode AS StudentContactPostCode,
                '' AS StudentContactState,
                c.PhoneHome AS StudentContactPhoneActual,
                '' AS StudentContactMobilePhone,
                c.PhoneWork AS StudentContactOccupPhone,
                '' AS StudentContactRelation,
                -- No speese
                '' AS StudentContactSpouseID,
                '' AS StudentContactSpouseTitle,
                '' AS StudentContactSpousePreferred,
                '' AS StudentContactSpouseSurname,
                '' AS StudentContactSpouseMobilePhone,
                '' AS StudentContactSpouseOccupPhone,
                '' AS StudentContactSpouseDefaultEmail,
                '' AS StudentContactType,
                '' AS StudentContactSpouseRelation
            FROM Contact c
            INNER JOIN StudentContact sc ON c.ID = sc.ContactID
            INNER JOIN Student s ON sc.StudentID = s.ID
            -- LEFT JOIN ContactFamily cf ON c.ID = cf.ContactID
            -- LEFT JOIN Family f ON cf.FamilyID = f.ID
            LEFT JOIN vw_Contact_Addresses ca ON c.ID = ca.ContactID
            WHERE s.Code = {$this->getDatabase()->smartQuote($externalUserId)}
        ";
        return $this->getDatabase()->getAll($query);
    }

    /**
     * @inheritDoc
     */
    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStudentUserAccountInfo()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getParentEmails($synergyIds)
    {
        if (empty($synergyIds)) {
            return [];
        }
        $query = "
            SELECT
                s.Code AS StudentID,
                c.Code AS ContactID,
                c.Email
            FROM Contact c
            INNER JOIN StudentContact sc ON c.ID = sc.ContactID
            INNER JOIN Student s ON sc.ContactID = s.ID
            WHERE c.Code IN (%s)
        ";
        $result = [];
        foreach ($this->getDatabase()->getAll($query) as $row) {
            $result[$row['StudentID'].'-'.$row['ContactID']] = $row['Email'];
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getParent($externalUserId)
    {
        $query = "
            SELECT
                c.Title AS Title,
                c.FirstName AS Preferred,
                c.LastName AS Surname,
                c.Email AS Email,
                c.WorkEmail as OccupEmail,
                c.PhoneFamily AS HomePhone,
                '' AS SilentPhoneFlag,
                c.PhoneMobile AS MobilePhone,
                '' AS DirectoryIncludeFlag,
                '' AS SpouseID,
                '' AS SpouseTitle,
                '' AS SpousePreferred,
                '' AS SpouseSurname,
                '' AS SpouseEmail,
                '' AS SpouseOccupEmail,
                '' AS SpouseDefaultEmailCode,
                '' AS SpouseMobilePhone,
                '' AS SpouseDirectoryIncludeFlag
            FROM Contact c
            WHERE c.Code = {$this->getDatabase()->smartQuote($externalUserId)}
        ";
        return $this->getDatabase()->getOne($query);
    }

    /**
     * @inheritDoc
     */
    public function getParentSync($externalUserId)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getParentUserAccountInfo()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStaff($externalUserId)
    {
        /**
         * Get the following fields for the staff member $externalUserId
         *
         * SchoolStaffCode,
         * StaffBuilding,
         * StaffRoom,
         * StaffExtension,
         * StaffMobilePhone,
         * StaffCampusDescription,
         * StaffCategoryDescription,
         * StaffDepartment,
         * StaffVoiceMail
         *
         * @param $externalUserId
         * @return array
         */
        $query = "
            SELECT
                '' AS SchoolStaffCode,
                '' AS StaffBuilding,
                '' AS StaffRoom,
                '' AS StaffExtension,
                t.Mobile AS StaffMobilePhone,
                '' AS StaffCampusDescription,
                '' AS StaffCategoryDescription,
                '' AS StaffDepartment,
                '' AS StaffVoiceMail
            FROM Staff t
            WHERE t.Code = {$this->getDatabase()->smartQuote($externalUserId)}
        ";
        return $this->getDatabase()->getOne($query);
    }

    /**
     * @inheritDoc
     */
    public function getStaffUserAccountInfo()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStaffContactListExtended()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getCurrentSemester($externalUserId = null)
    {
        return $this->database->getOne("SELECT Semester FROM SchoolPeriod WHERE IsCurrent = 'Y'");
    }

    /**
     * @inheritDoc
     */
    public function getCurrentYear($externalUserId = null)
    {
        return $this->database->getOne("SELECT Year FROM SchoolPeriod WHERE IsCurrent = 'Y'");
    }

    /**
     * @inheritDoc
     */
    public function getSubjectList($externalUserId, $userId)
    {
        try {
            $user = \Schoolbox\UserMapper::getInstance()->findById($userId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            return [];
        }

        $quotedId = $this->getDatabase()->smartQuote($externalUserId);
        if ($user->getRole()->isStudent()) {
            // Statement 1: core subjects
            // Statement 2: electives
            $query = "
                SELECT DISTINCT
                    s.Code + ISNULL(b.Suffix, '') AS code,
                    s.Name as name,
                    '' AS campusCode,
                    '' AS classType
                FROM tblSubject s
                INNER JOIN tblAllocation a on s.ID = a.SubjectID
                INNER JOIN tblStudent st on a.FormID = st.FormID
                LEFT JOIN tblBlockItem b on s.ID = b.SubjectID
                WHERE st.deleted = 0
                AND st.code = $quotedId

                UNION ALL

                SELECT DISTINCT
                    s.Code + ISNULL(b.Suffix, '') AS code,
                    s.Name AS name,
                    '' AS campusCode,
                    '' AS classType
                FROM tblSubject s
                INNER JOIN tblBlockItem b on s.ID = b.SubjectID
                INNER JOIN tblSubjectChoice sc ON b.YearBlockItemID = sc.YearBlockItemID
                INNER JOIN tblStudent st ON sc.StudentID = st.ID
                WHERE st.deleted = 0
                AND st.code = $quotedId

                ORDER BY code ASC
            ";
        } elseif ($user->getRole()->isStaff()) {
            $query = "
                SELECT DISTINCT
                    (s.[Code] + ISNULL(b.Suffix, '')) as code,
                    s.[Name] as name,
                    '' AS campusCode,
                    '' AS classType
                FROM [tblSubject] s
                left join [tblBlockItem] b on s.ID = b.SubjectID
                left join [tblTeacher] t on b.TeacherID = t.ID
                Where t.deleted = 0
                AND t.code = $quotedId
                Order by (s.[Code] + ISNULL(b.Suffix, '')) ASC
            ";
        } else {
            return [];
        }
        return $this->loadAPlusDatabase()->getAll($query);
    }

    /**
     * @inheritDoc
     */
    public function getEnrolledStudents($externalUserId)
    {
        $query = "
            SELECT
                s.Code AS StudentID
            FROM Student s
            LEFT JOIN StudentContact sc ON s.ID = sc.StudentID
            LEFT JOIN Contact c ON sc.ContactID = c.ID
            WHERE c.Code = {$this->getDatabase()->smartQuote($externalUserId)}
        ";
        $result = [];
        foreach ($this->getDatabase()->getAll($query) as $row) {
            $result[] = $row['StudentID'];
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getEmail($externalUserId)
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getPortrait($externalUserId)
    {
        // Copied from External_Schoolbox_User strategy
        $portraitPath = realpath( Config::get('portrait_dir') ) . "/$externalUserId.jpg";
        if ( is_readable($portraitPath) ) {
            return $portraitPath;
        } else {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function determineBoarderType($boarderValue)
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getHousemasterByHouseId($houseID)
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getCampusFromTimetable($staffID)
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getStudentForms()
    {
        return '';
    }

    /**
     * @param MSSQL $database
     */
    private function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return MSSQL
     */
    private function getDatabase()
    {
        return $this->database;
    }

    /**
     * Lazy-load A+ database connection
     *
     * @return MSSQL
     */
    private function loadAPlusDatabase()
    {
        if (!($this->aPlusDatabase instanceof MSSQL)) {
            $this->aPlusDatabase = new MSSQL(
                Config::get('external_humanedge_aplus_db_db'),
                [
                    'host'     => Config::get('external_humanedge_aplus_db_host'),
                    'user'     => Config::get('external_humanedge_aplus_db_user'),
                    'passwd'   => Config::get('external_humanedge_aplus_db_passwd'),
                    'db'       => Config::get('external_humanedge_aplus_db_db'),
                    'encoding' => Config::get('external_db_encoding'),
                ],
                true
            );
        }
        return $this->aPlusDatabase;
    }
}
