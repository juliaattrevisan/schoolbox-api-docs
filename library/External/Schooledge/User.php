<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Resource\Storage\Model;
use Schoolbox\User;

/**
 * Schooledge implementation of user retrieve methods.
 */
class External_Schooledge_User implements External_UserInterface
{

    /**
     * This identifies the CONVERT/CAST format for ISO8601 date
     * yyyy-mm-ddThh:mi:ss.mmm
     */
    protected const FORMAT_ISO8601 = 126;


    /**
     * @var MSSQL $database
     */
    private $database;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->database = MSSQL::getInstance();
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

        $quotedId    = $this->database->smartQuote($externalId);
        $campusCodes = array_map(
            [$this->database, 'smartQuote'],
            $user->getCampusCollection()->getColumnValues('extTimetableCode')
        );
        if (empty($campusCodes)) {
            return [];
        }
        $campusCodeString = implode(',', $campusCodes);
        if ($user->getRole()->isStudent()) {
            // 1st part: blocked classes
            // 2nd part: non-blocked classes
            $query = "
                SELECT DISTINCT
                    tc.Name     AS code,
                    ts.Name     AS name,
                    t.ShortName AS campusCode,
                    ''          AS classType
                FROM fc.TimetableSubject ts

                INNER JOIN fc.Timetable              t   ON ts.TimetableID = t.ID
                INNER JOIN fc.TimetableClass         tc   ON ts.ID             = tc.TimetableSubjectID
                INNER JOIN fc.TimetableClassInstance tci  ON tc.ID             = tci.TimetableClassID
                INNER JOIN fc.TimetableSubjectChoice tsc  ON tc.ID             = tsc.TimetableClassID
                INNER JOIN dbo.Student               s    ON tsc.PersonID      = s.PersonID
                
                WHERE s.Code = $quotedId         
                AND   t.Enabled = 1
                AND   t.Name IN ($campusCodeString)

                UNION

                SELECT DISTINCT
                    tc.Name     AS code,
                    ts.Name     AS name,
                    t.ShortName AS campusCode,
                    ''          AS classType

                FROM fc.TimetableSubject ts

                INNER JOIN fc.TimetableClass      tc   ON ts.ID             = tc.TimetableSubjectID
                INNER JOIN fc.YearLevelSub        yls  ON tc.YearLevelSubID = yls.ID
                INNER JOIN fc.YearLevelSubStudent ylss ON yls.ID            = ylss.YearLevelSubID
                INNER JOIN dbo.Student            s    ON ylss.PersonID     = s.PersonID
                INNER JOIN fc.Timetable           t    ON ts.TimetableID    = t.ID

                WHERE s.Code = $quotedId
                AND   t.Enabled = 1
                AND   t.Name IN ($campusCodeString)

                ORDER BY code ASC

            ";
        } elseif ($user->getRole()->isStaff()) {
            // As far as I can interpret:
            // Use base staff if there is no TimetableClassStaff in TimetableClassInstanceStaff
            // Use use replacement staff if it's found
            $query = "
                SELECT DISTINCT
                    tc.Name     AS code,
                    ts.Name     AS name,
                    t.ShortName AS campusCode,
                    ''          AS classType
                FROM fc.TimetableSubject ts

                -- Base join to timetable
                INNER JOIN fc.Timetable t ON ts.TimetableID = t.ID

                -- Base join to class
                INNER JOIN fc.TimetableClass tc ON ts.ID = tc.TimetableSubjectID

                -- Base staff
                LEFT  JOIN fc.TimetableClassStaff tcs        ON tc.ID        = tcs.TimetableClassId
                LEFT  JOIN dbo.Staff              staff_base ON tcs.PersonID = staff_base.PersonID

                -- Override staff
                LEFT  JOIN fc.TimetableClassInstance      tci               ON tc.ID         = tci.TimetableClassID
                LEFT  JOIN fc.TimetableClassInstanceStaff tcis              ON tci.ID        = tcis.TimetableClassInstanceId
                LEFT  JOIN dbo.Staff                      staff_replacement ON tcis.PersonID = staff_replacement.PersonID

                WHERE (
                    (staff_base.Code = $quotedId AND tcis.TimetableClassStaffID IS NULL)
                    OR
                    staff_replacement.Code = $quotedId
                )
                AND t.Enabled = 1
                AND t.Name IN ($campusCodeString)

                ORDER BY code ASC
            ";
        } else {
            return [];
        }
        return $this->database->getAll($query);
    }


    /**
     * @inheritDoc
     */
    public function getStudent(string $externalUserId): array
    {
        // Smoosh all of the contacts into one
        $query = "
            SELECT
                s.Code AS StudentID,
                s.FirstName AS StudentGiven1,
                s.LastName AS StudentSurname,
                '' AS StudentGender,
                CONVERT(nvarchar(30), s.DOB, ".self::FORMAT_ISO8601." )  AS StudentBirthDate,
                s.House AS StudentHouseDescription,
                s.House AS StudentHouse,
                '' AS HeadOfHouseID,
                '' AS StudentHeadOfHouse,
                s.BoarderType AS StudentBoarder,
                '' AS Comment,
                '' AS StudentEnrolmentDate,
                '' AS StudentLeavingDate,
                '' AS StudentIBFlag,
                s.Form AS StudentForm,
                '' AS StudentFormStaffName,
                '' AS StudentTutorName,
                s.HomeGroup AS StudentTutorDescription,
                '' AS TeacherFormID,
                '' AS TeacherTutorID,
                s.Year AS StudentYearLevel,
                '' AS YearLevelCoordID,
                '' AS StudentYearLevelCoordinator,
                (c.FirstName + ' ' + c.LastName) AS StudentEmergencyName,
                c.PhoneHome AS StudentEmergencyPhone,
                c.PhoneWork AS StudentEmergencyPhoneAlt,
                s.MedicalConditions AS StudentMedicalAlert,
                '' AS StudentMobilePhone,
                s.SpecialNeeds AS StudentSpecialNeedsFlag,
                '' AS EnglishSecondFlag,
                '' AS StudentStudiesCode,
                '' AS AlertMessage,
                '' AS CourtOrderType,
                0 AS countIndividualEducationPlans
            FROM Student s
            LEFT JOIN StudentContact sc ON s.ID = sc.StudentID AND sc.EmergencyContact = 'Y'
            LEFT JOIN Contact c ON sc.ContactID = c.ID
            WHERE s.Code = {$this->database->smartQuote($externalUserId)}
        ";
        $result = [];
        $i = 1;
        foreach ($this->database->getAll($query) as $row) {
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
    public function getStudentContactList(array $externalUserId): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        $ids = implode(',', $externalUserId);

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
            {$this->buildStudentContactAdhocJoins()}
            WHERE s.Code IN ($ids)
            AND
            (
              sc.ResidentialGuardian = 'Y' OR
              sc.PrimaryMailing = 'Y' OR
              (sc.FeeFactor<>0 AND sc.EmergencyContact = 'Y' AND sc.Relationship <> 'Grandmother')
            )
            AND ca.MailAddrID = ca.ID
            AND ({$this->buildStudentContactFilter()})
        ";
        return $this->database->getAll($query);
    }


    /**
     * @inheritDoc
     */
    public function getStudentContactListExtended(string $yearLevel, string $name, string $boardingStatus, string $form): array
    {
        return [];
    }


    /**
     * @inheritDoc
     */
    public function getStudentUserAccountInfo(): array
    {
        return [];
    }


    /**
     * @inheritDoc
     */
    public function getParentEmails(array $externalUserIds): array
    {
        if (empty($externalUserIds)) {
            return [];
        }

        $idString = implode(',', array_map([$this->database, 'smartQuote'], $externalUserIds));

        $query = "
            SELECT
                s.Code AS StudentID,
                c.Code AS ContactID,
                c.Email
            FROM Contact c
            INNER JOIN StudentContact sc ON c.ID = sc.ContactID
            INNER JOIN Student s ON sc.StudentID = s.ID
            {$this->buildStudentContactAdhocJoins()}
            WHERE s.Code IN ($idString)
            AND ({$this->buildStudentContactFilter()})
        ";

        $result = [];
        foreach ($this->database->getAll($query) as $row) {
            $result[$row['StudentID'].'-'.$row['ContactID']] = $row['Email'];
        }
        return $result;
    }


    /**
     * @inheritDoc
     */
    public function getParent(string $externalUserId): array
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
            WHERE c.Code = {$this->database->smartQuote($externalUserId)}
        ";
        return $this->database->getRow($query) ?: [];
    }


    /**
     * @inheritDoc
     */
    public function getParentSync(array $externalUserIds): array
    {
        return [];
    }


    /**
     * @inheritDoc
     */
    public function getParentUserAccountInfo(): array
    {
        return [];
    }


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
     * StaffVoiceMail,
     * StaffHouseDescription
     *
     * @param $externalUserId
     * @return array
     */
    public function getStaff(string $externalUserId): array
    {

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
                '' AS StaffVoiceMail,
                '' AS StaffHouseDescription
            FROM Staff t
            WHERE t.Code = {$this->database->smartQuote($externalUserId)}
        ";
        return $this->database->getRow($query) ?: [];
    }


    /**
     * @inheritDoc
     */
    public function getStaffUserAccountInfo(): array
    {
        return [];
    }


    /**
     * @inheritDoc
     */
    public function getStaffContactListExtended(): array
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
                t.TeachingDepartment AS StaffDepartment,
                '' AS StaffJobPositionStartDate,
                '' AS StaffJobPositionEndDate,
                t.Position AS JobPositionDescription,
                '' AS JobPositionDescriptionAlternate,
                ppp.PhoneType,
                ppp.Number
            FROM Staff t
            LEFT JOIN Person.PersonPhone ppp ON ppp.PersonID = t.PersonID AND ppp.Archived = 0
            LEFT JOIN Campus c ON t.CampusID = c.ID
            WHERE t.Deceased = 'N' 
            AND t.Active = 'Y'
        ";

        /* Get everyone
         */
        $allStaff = $this->database->getAll($query);

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
    public function getCurrentSemester(string $externalUserId = null): ?string
    {
        $semester = $this->database->getOne("SELECT Semester FROM SchoolPeriod WHERE IsCurrent = 'Y'");
        return ($semester === null || $semester === false) ? null : (string)$semester;
    }


    /**
     * @inheritDoc
     */
    public function getCurrentYear(string $externalUserId = null): ?string
    {
        $year = $this->database->getOne("SELECT Year FROM SchoolPeriod WHERE IsCurrent = 'Y'");
        return ($year === null || $year === false) ? null : (string)$year;
    }


    /**
     * @inheritDoc
     */
    public function getEnrolledStudents(string $externalUserId): array
    {
        $query = "
            SELECT
                s.Code AS RelatedID
            FROM Student s
            LEFT JOIN StudentContact sc ON s.ID = sc.StudentID
            LEFT JOIN Contact c ON sc.ContactID = c.ID
            {$this->buildStudentContactAdhocJoins()}
            WHERE c.Code = {$this->database->smartQuote($externalUserId)}
            AND ({$this->buildStudentContactFilter()})
        ";
        return $this->database->getAll($query);
    }


    /**
     * {@inheritDoc}
     *
     * @param User $user
     * @return Model|null
     */
    public function getPortrait(User $user): ?Model
    {
        return $user->getPortrait();
    }


    /**
     * @inheritDoc
     */
    public function determineBoarderType(?string $boarderValue): string
    {
        return '';
    }


    /**
     * @inheritDoc
     */
    public function getHousemasterByHouseId(string $houseID): ?string
    {
        return null;
    }


    /**
     * @inheritdoc
     */
    public function getHousemasterEmails(array $externalUserIds): array
    {
        return [];
    }


    /**
     * @inheritDoc
     */
    public function getCampusFromTimetable(string $staffID): array
    {
        return [];
    }


    /**
     * @inheritDoc
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
        return [];
    }


    /**
     * Builds a filter for a student's contacts.
     *
     * This filters out anyone who's not a primary guardian or parent.
     *
     * @return string
     */
    protected function buildStudentContactFilter(): string
    {
        return "
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
        ";
    }


    /**
     * Builds a set of joins onto the AdHoc table, if required.
     *
     * This default implementation requires no joins onto this table.
     *
     * @return string
     */
    protected function buildStudentContactAdhocJoins(): string
    {
        return '';
    }
}
