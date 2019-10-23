<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Resource\Storage;
use Schoolbox\User;

class External_Isams_User implements External_UserInterface
{
    use External_MssqlConnector;

    /**
     * This identifies the CONVERT/CAST format for ISO8601 date
     * yyyy-mm-ddThh:mi:ss.mmm
     */
    protected const FORMAT_ISO8601 = 126;

    /** Constructor */
    public function __construct()
    {
        $this->setupDatabase();
    }


    /**
     * {@inheritDoc}
     *
     * @param User $user
     * @return Storage\Model|null
     */
    public function getPortrait(User $user): ?Storage\Model
    {
        return $user->getPortrait();
    }


    /**
     * @inheritDoc
     */
    public function getParent(string $externalUserId): array
    {
        $query = "
            SELECT TOP 1
                        V_Parent.ParentID             AS ID,
                        V_Parent.Title                AS Title,
                        V_Parent.ParentFirstname      AS Preferred,
                        V_Parent.ParentSurname        AS Surname,
                        V_Parent.PersonalEmail             AS Email,
                        ''                            AS OccupEmail,
                        ''                            AS DefaultEmailCode,
                        ''                            AS HomePhone,
                        V_Parent.Mobile               AS MobilePhone,
                        0                             AS SilentPhoneFlag,
                        0                             AS SilentMobilePhoneFlag,
                        1                             AS DirectoryIncludeFlag,
                        ''                            AS SpouseID,
                        ''                            AS SpouseTitle,
                        ''                            AS SpousePreferred,
                        ''                            AS SpouseSurname,
                        ''                            AS SpouseEmail,
                        ''                            AS SpouseOccupEmail,
                        ''                            AS SpouseDefaultEmailCode,
                        ''                            AS SpouseMobilePhone,
                        1                             AS SpouseDirectoryIncludeFlag
            FROM        V_Student
            LEFT JOIN   V_Parent AS V_Parent ON V_Parent.StudentID = :external_user_id
            WHERE       V_Student.StudentID = :external_user_id
        ";

        return $this->getConnector()->executeQuery($query, ['external_user_id' => $externalUserId])->fetch() ?: [];
    }

    /**
     * I gots me no frigging clue what the difference here is...
     *
     * @param array $externalUserIds
     * @return array
     */
    public function getParentSync(array $externalUserIds): array
    {
        $parents = [];
        foreach ($externalUserIds as $externalUserId) {
            $parents[] = $this->getParent($externalUserId);
        }
        return $parents;
    }

    /**
     * Get all the parents emails for a class, or group of students
     *
     * @param array $externalUserIds
     * @return array
     */
    public function getParentEmails(array $externalUserIds): array
    {
        $emailList = [];
        $pb = $this->getConnector()->buildPredicateBuilder("V_Parent")->in("StudentID", $externalUserIds);
        $pb->notEquals("PersonalEmail", "");
        $whereString = implode(" AND " , $pb->getWhere());

        $query =
        "
            SELECT V_Parent.ParentID, V_Parent.PersonalEmail 
            FROM V_Parent
            WHERE $whereString
        ";

        $iterator = $this->getConnector()->getQueryIterator($query, $pb->getParams());

        foreach ($iterator as $currentRow) {
            if (!isset($emailList[$currentRow['ParentID']])) {
                $emailList[$currentRow['ParentID']] = $currentRow['PersonalEmail'];
            }
        }

        return $emailList;
    }

    /**
     * @inheritDoc
     */
    public function getStudent(string $externalUserId): array
    {
        $query = "
            SELECT TOP 1
                        StudentID               AS StudentID,
                        StudentFirstname        AS StudentGiven1,
                        StudentSurname          AS StudentSurname,
                        ''                      AS StudentGender,
                        CONVERT(nvarchar(30), DOB, ".self::FORMAT_ISO8601." ) AS StudentBirthDate,                
                        ''                      AS StudentTutorName,
                        ''                      AS StudentHeadOfHouse,
                        ''                      AS HeadOfHouseID,
                        ''                      AS StudentFormStaffName,
                        ''                      AS StudentTutorDescription,
                        ''                      AS TeacherFormID,
                        ''                      AS StudentForm,
                        ''                      AS TeacherTutorID,
                        ''                      AS StudentHouseDescription,
                        ''                      AS StudentHouse,
                        Grade                   AS StudentYearLevel,
                        ''                      AS StudentEmergencyName,
                        ''                      AS StudentEmergencyPhone, 
                        ''                      AS StudentEmergencyPhoneAlt,
                        txtadditionalHealth     AS StudentMedicalAlert,
                        ''                      AS CourtOrderType,
                        ''                      AS StudentMobilePhone,
                        ''                      AS StudentSpecialNeedsFlag,
                        ''                      AS EnglishSecondFlag,
                        ''                      AS StudentStudiesCode,
                        ''                      AS StudentBoarder,
                        ''                      AS StudentIBFlag,
                        EnrolmentDate           AS StudentEnrolmentDate,
                        LeavingDate             AS StudentLeavingDate,
                        0 AS countIndividualEducationPlans
            FROM        V_Student
            WHERE       StudentID = :external_user_id
        ";

        return $this->getConnector()->executeQuery($query, ['external_user_id' => $externalUserId])->fetch() ?: [];
    }


    /**
     * @inheritDoc
     * @throws \Alaress\Database\Exception
     */
    public function getStaff(string $externalUserId): array
    {
        $staff = "
            SELECT          StaffID             AS SchoolStaffCode,
                            ''                  AS StaffBuilding,
                            ''                  AS StaffRoom,
                            ''                  AS StaffExtension,
                            ''                  AS StaffMobilePhone,
                            ''                  AS StaffDefaultMobilePhone,
                            Campus              AS StaffCampusDescription,
                            DivisionID          AS StaffCategoryDescription,
                            ''                  AS StaffDepartment,
                            ''                  AS StaffVoiceMail,
                            ''                  AS StaffHouseDescription
            FROM            V_Staff
            WHERE           StaffID = :external_user_id
        ";

        return $this->getConnector()->executeQuery($staff, ['external_user_id' => $externalUserId])->fetch() ?: [];
    }


    /**
     * @inheritDoc
     * @throws \Alaress\Database\Exception
     */
    public function getStudentContactListExtended(string $yearLevel, string $name, string $boardingStatus, string $form): array
    {
        $query = "
            SELECT      
                        V_Student.StudentID           AS StudentID,
                        V_Student.StudentFirstName    AS StudentPreferred,
                        V_Student.StudentSurname      AS StudentSurname,
                        V_Student.Grade               AS StudentYearLevel,
                        ''                            AS StudentHouse,
                        ''                            AS StudentBoarder,
                        ''                            AS StudentForm,
                        V_Parent.ParentID             AS StudentContactID,
                        V_Parent.Title                AS StudentContactTitle,
                        V_Parent.ParentFirstname      AS StudentContactPreferred,
                        V_Parent.ParentFirstname      AS StudentContactLegalFullName,
                        V_Parent.ParentSurname        AS StudentContactSurname,
                        V_Parent.PersonalEmail        AS StudentContactDefaultEmail,
                        1                             AS ValidAddressFlag,
                        V_Parent.Address1             AS StudentContactAddress1,
                        V_Parent.Address2             AS StudentContactAddress2,
                        V_Parent.Address3             AS StudentcontactAddress3,
                        ''                            AS StudentContactSuburb,
                        V_Parent.PostCode             AS StudentContactPostCode,
                        ''                            AS StudentContactState,
                        V_Parent.Country              AS StudentContactCountryCode,

                        V_Parent.Address1             AS StudentContactHomeAddress1,
                        V_Parent.Address2             AS StudentContactHomeAddress2,
                        V_Parent.Address3             AS StudentContactHomeAddress3,
                        ''                            AS StudentContactHomeSuburb,
                        V_Parent.PostCode             AS StudentContactHomePostCode,
                        ''                            AS StudentContactHomeState,
                        V_Parent.Country              AS StudentContactHomeCountryCode,
                        V_Parent.Mobile               AS StudentContactMobilePhone,
                        ''                            AS StudentContactOccupPhone,
                        V_Parent.Relationship         AS StudentContactRelation,
                        

                        ''                            AS StudentContactSpouseID,
                        ''                            AS StudentContactSpouseTitle,
                        ''                            AS StudentContactSpousePreferred,
                        ''                            AS StudentContactSpouseSurname,
                        ''                            AS StudentContactSpouseMobilePhone,
                        ''                            AS StudentContactSpouseOccupPhone, 
                        ''                            AS StudentContactSpouseDefaultEmail,
                        'SC1'                         AS StudentContactType,
                        0                             AS StudentContactSilentPhoneFlag,
                        0                             AS StudentContactSilentMobilePhoneFlag,
                        1                             AS PrimaryDirectoryIncludeFlag,
                        1                             AS SpouseDirectoryIncludeFlag,
                        0                             AS SilentHomeAddressFlag,
                        0                             AS StudentParentsSeparatedFlag

            FROM        V_Student
            JOIN        V_Parent ON V_Parent.StudentID = V_Student.StudentID
        ";

        if (!empty($yearLevel)) {
            $query .= "WHERE V_Student.Grade = :year_level";
        }

        $query .= " ORDER BY StudentSurname, StudentPreferred, StudentID";

        return $this->getConnector()->executeQuery($query, ['year_level' => $yearLevel])->fetchAll();
    }

    /**
     * @inheritDoc
     */
    public function getStudentContactList(string $externalUserId): array
    {
        $query = "
            SELECT      
                        V_Parent.ParentID             AS StudentContactID,
                        V_Parent.Title                AS StudentContactTitle,
                        V_Parent.ParentFirstname      AS StudentContactPreferred,
                        V_Parent.ParentSurname        AS StudentContactSurname,
                        V_Parent.PersonalEmail             AS StudentContactDefaultEmail,
                        V_Parent.Mobile               AS StudentContactMobilePhone,
                        ''                            AS StudentContactOccupPhone,
                        ''                            AS StudentContactPhoneActual,
                        0                             AS StudentContactSilentPhoneFlag,
                        V_Parent.Relationship         AS StudentContactRelation,
                        ''                            AS StudentContactSpouseID,
                        ''                            AS StudentContactSpouseTitle,
                        ''                            AS StudentContactSpousePreferred,
                        ''                            AS StudentContactSpouseSurname,
                        ''                            AS StudentContactSpouseMobilePhone,
                        ''                            AS StudentContactSpouseOccupPhone, 
                        ''                            AS StudentContactSpouseDefaultEmail
            FROM        V_Student
            JOIN        V_Parent ON V_Parent.StudentID = V_Student.StudentID 
            WHERE       V_Student.StudentID = :external_user_id
        ";

        return $this->getConnector()->executeQuery($query , ['external_user_id' => $externalUserId])->fetchAll();
    }

    /**
     * @inheritDoc
     */
    public function getCurrentSemester(string $externalUserId = null): ?string
    {
        return date('m') < 6 ? '1' : '2';
    }

    /**
     * @inheritDoc
     */
    public function getCurrentYear(string $externalUserId = null): ?string
    {
        return date("Y");
    }

    /**
     * @inheritDoc
     */
    public function getSubjectList(User $user): array
    {
        $externalUserId = $user->getExternalId();

        $sql = '
          SELECT DISTINCT   V_Class.ClassCode AS code
          FROM              V_Class
          WHERE             V_Class.StudentID = :external_user_id OR
                            V_Class.Tutor1ID = :external_user_id OR
                            V_Class.Tutor2ID = :external_user_id
        ';

        $iterator = $this->getConnector()->getQueryIterator($sql, ['external_user_id' => $externalUserId]);
        $subjectList = [];

        foreach ($iterator as $currentRow) {
            $subjectList[] = array(
                'code' => $currentRow['code'],
                'name' => $currentRow['code'],
                'classType' => ''
            );
        }

        return $subjectList;
    }


    /**
     * @inheritDoc
     * @throws \Alaress\Database\Exception
     */
    public function getEnrolledStudents(string $externalUserId): array
    {
        $query = "
            SELECT    StudentID AS RelatedID
            FROM      V_Parent   
            WHERE     V_Parent.ParentID = :external_user_id
        ";

        return $this->getConnector()->executeQuery($query, ['external_user_id' => $externalUserId])->fetchAll();
    }


    /**
     * @inheritDoc
     * @throws \Alaress\Database\Exception
     */
    public function getStudentUserAccountInfo(): array
    {
        $query = "
            SELECT      StudentID               AS StudentID,
                        Username                AS NetworkLogin,
                        Title                   AS StudentTitle,
                        StudentFirstname        AS StudentPreferred,
                        StudentSurname          AS StudentSurname,
                        Gender                  AS StudentGender,
                        StudentEmail            AS StudentEmail,
                        ''                      AS StudentOccupEmail,
                        Grade                   AS StudentYearLevel,
                        ''                      AS StudentHouse,
                        ''                      AS StudentBoarder,
                        ''                      AS StudentBoardingHouse,
                        campus                  AS StudentCampus
            FROM        V_Student
        ";

        return $this->getConnector()->executeQuery($query)->fetchAll();
    }


    /**
     * @inheritDoc
     * @throws \Alaress\Database\Exception
     */
    public function getParentUserAccountInfo(): array
    {
        $query = "
            SELECT      
                        V_Parent.Username             AS StudentContactNetworkLogin,
                        V_Parent.ParentID             AS StudentContactID,
                        V_Parent.Title                AS StudentContactTitle,
                        V_Parent.ParentFirstname      AS StudentContactPreferred,
                        V_Parent.ParentSurname        AS StudentContactSurname,
                        V_Parent.PersonalEmail        AS StudentContactDefaultEmail,
                        ''                            AS StudentContactSpouseNetworkLogin,
                        ''                            AS StudentContactSpouseID,
                        ''                            AS StudentContactSpouseTitle,
                        ''                            AS StudentContactSpousePreferred,
                        ''                            AS StudentContactSpouseSurname,
                        ''                            AS StudentContactSpouseDefaultEmail,
                        V_Student.Grade               AS StudentYearLevel,
                        V_Student.campus              AS StudentCampus,
                        V_Student.Gender              AS StudentGender
            FROM        V_Student
            LEFT JOIN   V_Parent AS V_Parent ON V_Parent.StudentID = V_Student.StudentID
        ";

        return $this->getConnector()->executeQuery($query)->fetchAll();
    }


    /**
     * @inheritDoc
     * @throws \Alaress\Database\Exception
     */
    public function getStaffUserAccountInfo(): array
    {
        $query = "
            SELECT      StaffID           AS StaffID,
                        Username          AS NetworkLogin,
                        1                 AS ActiveFlag,
                        title             AS StaffTitle,
                        PreferredName     AS StaffPreferredName,
                        teacherlastname   AS StaffSurname,
                        'o'               AS DefaultEmailCode,
                        Email             AS OccupEmail,
                        ''                AS Email,
                        Campus            AS StaffCampus,
                        ''                AS StaffCategoryType,
                        ''                AS StaffCategory,
                        ''                AS StaffDepartment
            FROM        V_Staff
            ORDER BY    teacherlastname, PreferredName
        ";

        return $this->getConnector()->executeQuery($query)->fetchAll();

    }

    /**
     * Contact list for staff directory
     * Exclude people who aren't active.
     * Exclude people who are on leave (category description for this is 'Staff on leave')
     * TODO: Make exclusion on category configurable, currently excluding
     *
     * @return array
     */
    public function getStaffContactListExtended(): array
    {
        $query = "
            SELECT      StaffID           AS StaffID,
                        Initials          AS SchoolStaffCode,
                        title             AS StaffTitle,
                        PreferredName     AS StaffPreferredName,
                        teacherlastname   AS StaffSurname,
                        ''                AS StaffOccupPhone,
                        Email             AS StaffOccupEmail,
                        ''                AS StaffExtension,
                        ''                AS StaffCampusDescription,
                        ''                AS StaffCategory,
                        ''                AS StaffCategoryType,
                        ''                AS StaffDefaultMobilePhone,
                        ''                AS StaffVoiceMail,
                        ''                AS StaffDepartment,
                        ''                AS JobPositionDescription,
                        EnrolmentDate     AS StaffJobPositionStartDate,
                        ''                AS StaffJobPositionEndDate,
                        Username          AS NetworkLogin,
                        1                 AS ActiveFlag,
                        'o'               AS DefaultEmailCode,
                        ''                AS Email,
                        Campus            AS StaffCampus,
                        ''                AS StaffCategoryType,
                        ''                AS StaffCategory,
                        ''                AS StaffDepartment
            FROM        V_Staff
            ORDER BY    teacherlastname, PreferredName
        ";

        return $this->getConnector()->executeQuery($query)->fetchAll();
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
    public function determineBoarderType(?string $boarderValue): string
    {
        return "Day Student";
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
     * {@inheritdoc}
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
        $query =" 
            SELECT DISTINCT Grade AS Code, Grade AS Description
            FROM        V_Student
            WHERE ISNULL(Grade, '') != ''            
        ";
        return $this->getConnector()->executeQuery($query)->fetchAll();
    }
}
