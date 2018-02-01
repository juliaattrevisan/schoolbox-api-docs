<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * HumanEdge implementation of user retrieve methods.
 */

use Schoolbox\User;

class External_Humanedge_User implements External_UserInterface
{
    use External_Humanedge_APlusDatabaseTrait;

    /**
     * By default, teacher-subject relations require that the timetable entry
     * has a room attached to it.
     *
     * @var bool
     */
    protected $requireTeacherSubjectsHaveRooms = true;

    /**
     * @var MSSQL $database
     */
    private $database;

    /**
     * The filter for a student's contacts - this filters out anyone who's not
     * a primary guardian or parent.
     *
     * @var string
     */
    protected $studentContactFilter = "(
        sc.ResidentialGuardian = 'Y' OR
        sc.Relationship = 'Father' OR
        sc.Relationship = 'Mother' OR
        sc.Relationship = 'Stepfather' OR
        sc.Relationship = 'Stepmother' OR
        sc.Relationship = 'Step Father' OR
        sc.Relationship = 'Step Mother' OR
        sc.Relationship = 'Houseparents' OR
        sc.Relationship LIKE '%Guardian%' OR
        sc.Relationship LIKE 'Foster%' OR
        sc.Relationship = 'Carer'
    )";

    /**
     * This identifies the CONVERT/CAST format for ISO8601 date
     * yyyy-mm-ddThh:mi:ss.mmm
     */
    const FORMAT_ISO8601 = 126;

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
                CONVERT(nvarchar(30), s.DOB, ".self::FORMAT_ISO8601." )  AS StudentBirthDate,
                '' AS StudentHouseDescription,
                s.House AS StudentHouse,
                '' AS HeadOfHouseID,
                '' AS StudentHeadOfHouse,
                s.BoarderType AS StudentBoarder,
                '' AS Comment,
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
                '' AS AlertMessage,
                0 AS countIndividualEducationPlans
            FROM Student s
            LEFT JOIN StudentContact sc ON s.ID = sc.StudentID AND sc.EmergencyContact = 'Y'
            LEFT JOIN Contact c ON sc.ContactID = c.ID
            WHERE s.Code = {$this->getDatabase()->smartQuote($externalUserId)}
        ";
        $result = [];
        $i = 1;
        foreach ($this->getDatabase()->getAll($query) as $row) {
            if (empty($result)) {
                if ($row['StudentBirthDate'] && $birthDate = strtotime($row['StudentBirthDate'])) {
                    $row['StudentBirthDate'] = $birthDate;
                } else {
                    $row['StudentBirthDate'] = false;
                }

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
                c.PhoneHome AS StudentContactPhoneActual,
                0 AS StudentContactSilentPhoneFlag,
                c.PhoneMobile AS StudentContactMobilePhone,
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
            AND
            (
              sc.ResidentialGuardian = 'Y' OR
              sc.PrimaryMailing = 'Y' OR
              (sc.FeeFactor<>0 AND sc.EmergencyContact = 'Y' AND sc.Relationship <> 'Grandmother')
            )
            AND ca.MailAddrID = ca.ID
            AND {$this->studentContactFilter}
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

        $idString = implode(',', array_map([$this->getDatabase(), 'smartQuote'], $synergyIds));

        $query = "
            SELECT
                s.Code AS StudentID,
                c.Code AS ContactID,
                c.Email
            FROM Contact c
            INNER JOIN StudentContact sc ON c.ID = sc.ContactID
            INNER JOIN Student s ON sc.StudentID = s.ID
            WHERE s.Code IN ($idString)
            AND {$this->studentContactFilter}
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
                t.Mobile AS StaffDefaultMobilePhone,
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
        $query = "
            SELECT      
                t.Code AS StaffID,
                t.Code AS SchoolStaffCode,
                t.Title AS StaffTitle,
                    CASE WHEN (LEN(t.PreferredName)>0) 
                    THEN t.PreferredName 
                    ELSE t.FirstName END 
                    AS StaffPreferredName,
                t.LastName AS StaffSurname,
                '' AS StaffOccupPhone,
                COALESCE(
                    CASE WHEN (LEN(t.Email2) > 0) THEN t.Email2 ELSE NULL END,
                    CASE WHEN (LEN(t.Email) > 0) THEN t.Email ELSE NULL END
                ) AS StaffOccupEmail,
                '' AS StaffExtension,
                c.Name AS StaffCampusDescription,
                '' AS StaffCategory,
                '' AS StaffCategoryType,
                t.Mobile AS StaffDefaultMobilePhone,
                '' AS StaffVoiceMail,
                t.TeachingDepartment AS StaffDepartmentDescription,
                '' AS StaffJobPositionStartDate,
                '' AS StaffJobPositionEndDate,
                t.Position AS JobPositionDescription,
                '' AS JobPositionDescriptionAlternate,
                ppp.PhoneType,
                ppp.Number
            FROM Staff t
            LEFT JOIN Person.PersonPhone ppp ON ppp.PersonID = t.PersonID
            LEFT JOIN Campus c ON t.CampusID = c.ID
            WHERE t.Deceased = 'N' 
            AND t.Active = 'Y'
        ";

        /* Get everyone
         */
        $allStaff = $this->getDatabase()->getAll($query);

        /* Each person can have 0..N "phone" fields. This is really a mechanism of contact
         * and includes home/family/business/email/mobile/other records for some reason so
         * we need to filter out and select the data we want
         */
        $processed = [];

        foreach ($allStaff as $currentStaff) {
            if (!isset($processed[$currentStaff['SchoolStaffCode']])) {
                $processed[$currentStaff['SchoolStaffCode']] = $currentStaff;
            }

            /* Assign Business Phone
             */
            if ($currentStaff['PhoneType'] == 'Business') {
                $processed[$currentStaff['SchoolStaffCode']]['StaffOccupPhone'] = $currentStaff['Number'];
            }

            /* Assign Extension
             */
            if ($currentStaff['PhoneType'] == 'Other') {
                $processed[$currentStaff['SchoolStaffCode']]['StaffExtension'] = $currentStaff['Number'];
            }
        }

        return array_values($processed);

//        $queryDebug = "SELECT Distinct TABLE_NAME FROM information_schema.TABLES";
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
     * Returns list of subjects the user is enrolled in
     *
     * @param User $user
     * @return array
     */
    public function getSubjectList(User $user)
    {
        $externalId = $user->getExternalId();

        if (empty($externalId)) {
            return [];
        }

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
                INNER JOIN tblPeriod p ON a.PeriodID = p.ID
                LEFT JOIN tblBlockItem b on s.ID = b.SubjectID
                WHERE st.deleted = 0
                AND st.code = :externalId

                UNION

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
                AND st.code = :externalId

                ORDER BY code ASC
            ";
        } elseif ($user->getRole()->isStaff()) {
            // Statement 1: core subjects
            // Statement 2: electives
            $roomJoin = ($this->requireTeacherSubjectsHaveRooms ? 'INNER JOIN tblRoom r on r.ID = b.RoomID' : '');
            $query    = "
                SELECT DISTINCT
                    s.Code + ISNULL(b.Suffix, '') as code,
                    s.Name as name,
                    '' AS campusCode,
                    '' AS classType
                FROM tblSubject s
                INNER JOIN tblAllocation a on s.ID = a.SubjectID
                INNER JOIN tblTeacher t on a.TeacherID = t.ID
                LEFT JOIN tblBlockItem b on s.ID = b.SubjectID
                $roomJoin
                WHERE t.deleted = 0
                AND t.code = :externalId

                UNION

                SELECT DISTINCT
                    s.Code + ISNULL(b.Suffix, '') as code,
                    s.Name as name,
                    '' AS campusCode,
                    '' AS classType
                FROM tblSubject s
                INNER JOIN tblBlockItem b on s.ID = b.SubjectID
                INNER JOIN tblTeacher t on b.TeacherID = t.ID
                $roomJoin
                WHERE t.deleted = 0
                AND t.code = :externalId

                ORDER BY code ASC
            ";
        } else {
            return [];
        }
        return $this->loadAPlusDatabase()
                    ->executeQuery($query, ['externalId' => $externalId])
                    ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @inheritDoc
     */
    public function getEnrolledStudents($externalUserId)
    {
        $query = "
            SELECT
                s.Code AS RelatedID
            FROM Student s
            LEFT JOIN StudentContact sc ON s.ID = sc.StudentID
            LEFT JOIN Contact c ON sc.ContactID = c.ID
            WHERE c.Code = {$this->getDatabase()->smartQuote($externalUserId)}
            AND {$this->studentContactFilter}
        ";
        return $this->getDatabase()->getAll($query);
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
     * @inheritdoc
     */
    public function getHousemasterEmails($externalUserIds)
    {
        return [];
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
    protected function getDatabase()
    {
        return $this->database;
    }
}
