<?php
/**
 * Isams User Object
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 *
 * Uncle iSam?
 *    _..._
 *   |||||||
 *   |||||||
 *  _|*****|_
 * ( '-...-' )
 * {`;-----;`}
 * {(| a  a|)}
 *  \|  _\ |/
 *   \  -  /
 *    '{_}`
 */

use Schoolbox\User;

class External_Isams_User implements External_UserInterface
{


    use External_MssqlConnector;

    /**
     * This identifies the CONVERT/CAST format for ISO8601 date
     * yyyy-mm-ddThh:mi:ss.mmm
     */
    const FORMAT_ISO8601 = 126;

    /** @var Group $groupHandler */
    private $groupHandler;

    /** Constructor */
    public function __construct()
    {
        $this->setupDatabase();
        $this->setGroup(new Group());
    }

    /**
     * Retrieves the user's image
     *
     * @param Integer $externalUserId
     * @return Bool|String
     */
    public function getPortrait($externalUserId)
    {
        $photoPath = realpath($this->config->get('portrait_dir')) . '/' . $externalUserId . '.jpg';

        if ($this->config->get('photo_sis_import') == 0) {
            if (file_exists($photoPath)) {
                return $photoPath;
            }
        }

        return false;
    }

    /**
     * Gets the parents of student
     * @param $externalUserId
     * @return mixed
     */
    public function getParent($externalUserId)
    {
        $query = "
            SELECT      V_Parent.ParentID             AS ID,
                        V_Parent.Title                AS Title,
                        V_Parent.ParentFirstname      AS Preferred,
                        V_Parent.ParentSurname        AS Surname,
                        V_Parent.CISEmail             AS Email,
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

        $parent = $this->getConnector()->executeQuery($query, ['external_user_id' => $externalUserId])->fetch();

        return $parent;
    }

    /**
     * I gots me no frigging clue what the difference here is...
     * @param $externalUserId
     * @return mixed
     */
    public function getParentSync($externalUserId)
    {
        return $this->getParent($externalUserId);
    }

    /**
     * Get all the parents emails for a class, or group of students
     * @param array $synergyIds
     * @return array
     */
    public function getParentEmails($synergyIds)
    {
        $emailList = [];
        $pb = $this->getConnector()->buildPredicateBuilder("V_Parent")->in("StudentID", $synergyIds);
        $pb->notEquals("CISEmail", "");
        $whereString = implode(" AND " , $pb->getWhere());

        $query =
        "
            SELECT V_Parent.ParentID, V_Parent.CISEmail 
            FROM V_Parent
            WHERE $whereString
        ";

        $iterator = $this->getConnector()->getQueryIterator($query, $pb->getParams());

        foreach ($iterator as $currentRow) {
            if (!isset($emailList[$currentRow['ParentID']])) {
                $emailList[$currentRow['ParentID']] = $currentRow['CISEmail'];
            }
        }

        return $emailList;
    }

    /**
     * Get a students info
     *
     * @param  $externalUserId
     * @return array|bool
     */
    public function getStudent($externalUserId)
    {
        $query = "
            SELECT      StudentID               AS StudentID,
                        StudentFirstname        AS StudentGiven1,
                        StudentSurname          AS StudentSurname,
                        CONVERT(nvarchar(30), DOB, ".self::FORMAT_ISO8601." ) AS StudentBirthDate,                
                        ''                      AS StudentTutorName,
                        ''                      AS StudentHeadOfHouse,
                        ''                      AS HeadOfHouseID,
                        ''                      AS StudentFormStaffName,
                        ''                      AS StudentTutorDescription,
                        ''                      AS TeacherFormID,
                        ''                      AS StudentForm,
                        ''                      AS TeacherTutorID,
                        ''                      AS StudentTutor,
                        ''                      AS FormDescription,
                        ''                      AS StudentHouseDescription,
                        ''                      AS StudentHouse,
                        Grade                   AS StudentYearLevel,
                        ''                      AS StudentLockerBook,
                        ''                      AS StudentEmergencyName,
                        ''                      AS StudentEmergencyPhone, 
                        ''                      AS StudentEmergencyPhoneAlt,
                        txtadditionalHealth     AS StudentMedicalAlert,
                        ''                      AS StudentSpecialNeedsFlag,
                        ''                      AS StudentStudiesCode,
                        ''                      AS StudentBoarder,
                        0 AS countIndividualEducationPlans
            FROM        V_Student
            WHERE       StudentID = :external_user_id
        ";

        return $this->getConnector()->executeQuery($query, ['external_user_id' => $externalUserId])->fetch();
    }

    /**
     * Retrieve selected staff members details
     * @param $externalUserId
     * @return array|bool
     */
    public function getStaff($externalUserId)
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

        return $this->getConnector()->executeQuery($staff, ['external_user_id' => $externalUserId])->fetch();
    }

    /**
     * Gets a list of Contacts for all Students in a particular year level.
     * @param $yearlevel
     * @param $name
     * @param $boardingStatus
     * @param $form
     * @return mixed
     */
    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form)
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
                        V_Parent.CISEmail             AS StudentContactDefaultEmail,
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

        if (!empty($yearlevel)) {
            $query .= "WHERE V_Student.Grade = :year_level";
        }

        $query .= " ORDER BY StudentSurname, StudentGiven1, StudentID";

        return $this->getConnector()->executeQuery($query, ['year_level' => $yearlevel])->fetchAll();
    }

    /**
     * Get a list of student contacts (parents) and their contact information
     * @param $externalUserId
     * @return mixed
     */
    public function getStudentContactList($externalUserId)
    {
        $query = "
            SELECT      
                        V_Parent.ParentID             AS StudentContactID,
                        V_Parent.Title                AS StudentContactTitle,
                        V_Parent.ParentFirstname      AS StudentContactPreferred,
                        V_Parent.ParentSurname        AS StudentContactSurname,
                        V_Parent.CISEmail             AS StudentContactDefaultEmail,
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
     * Retrieve the current semester, same old thing
     *
     * @param null $externalUserId
     * @return int
     */
    public function getCurrentSemester($externalUserId = null)
    {
        return date('m') < 6 ? 1 : 2;
    }

    /**
     * Get current academic year
     *
     * @param null $externalUserId
     * @return array|bool
     */
    public function getCurrentYear($externalUserId = null)
    {
        return date("Y");
    }

    /**
     * Get a list of subjects for a given student
     *
     * @param User $user
     * @return array
     */
    public function getSubjectList(User $user)
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
     * Get the students belonging to specified person
     *
     * @param $externalUserId
     * @return array|bool
     */
    public function getEnrolledStudents($externalUserId)
    {
        $query = "
            SELECT    StudentID AS RelatedID
            FROM      V_Parent   
            WHERE     V_Parent.ParentID = :external_user_id
        ";

        return $this->getConnector()->executeQuery($query, ['external_user_id' => $externalUserId])->fetchAll();
    }

    /**
     * Retrieve email address
     * @param $id
     * @return array|bool
     */
    public function getEmail($id)
    {
        $query =
            "
          SELECT  V_Parent.CISEmail 
          FROM    V_Parent 
          WHERE   V_Parent.ParentID = :id
        ";

        return $this->getConnector()->executeQuery($query, ['id' => $id])->fetch();
    }

    /**
     * Gets all Students' account info
     *
     * @return array|bool
     */
    public function getStudentUserAccountInfo()
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
     * Gets all Parents' account info
     *
     * @return array|bool
     */
    public function getParentUserAccountInfo()
    {
        $query = "
            SELECT      
                        Username                      AS StudentContactNetworkLogin,
                        V_Parent.ParentID             AS StudentContactID,
                        V_Parent.Title                AS StudentContactTitle,
                        V_Parent.ParentFirstname      AS StudentContactPreferred,
                        V_Parent.ParentSurname        AS StudentContactSurname,
                        V_Parent.CISEmail             AS StudentContactDefaultEmail,
                        ''                            AS StudentContactSpouseNetworkLogin,
                        ''                            AS StudentContactSpouseID,
                        ''                            AS StudentContactSpouseTitle,
                        ''                            AS StudentContactSpousePreferred,
                        ''                            AS StudentContactSpouseSurname,
                        ''                            AS StudentContactSpouseDefaultEmail,
                        V_Student.Grade               AS StudentYearLevel,
                        campus                        AS StudentCampus,
                        Gender                        AS StudentGender
            FROM        V_Student
            LEFT JOIN   V_Parent AS V_Parent ON V_Parent.StudentID = V_Student.StudentID
        ";

        return $this->getConnector()->executeQuery($query)->fetchAll();
    }


    /**
     * Gets all active Staff's account info
     *
     * @return array|bool
     */
    public function getStaffUserAccountInfo()
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
     * @return mixed
     */
    public function getStaffContactListExtended()
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
                        ''                AS StaffDepartmentDescription,
                        ''                AS JobPositionDescription,
                        EnrolmentDate     AS StaffJobPositionStartDate,
                        ''                AS StaffJobPositionEndDate,
                        Username          AS NetworkLogin,
                        1                 AS ActiveFlag,
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
     * List of campuses a staff member is involved in based on timetable
     *
     * @param int $staffID
     * @return array|bool
     */
    public function getCampusFromTimetable($staffID)
    {
        return [];
    }

    /**
     * Determine the boarder type based on a value
     * Returns a human-readable string
     *
     * @param $boarderValue
     * @return string
     */
    public function determineBoarderType($boarderValue)
    {
        return "Day Student";
    }

    /**
     * @param $houseID
     * @return bool
     */
    public function getHousemasterByHouseId($houseID)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getHousemasterEmails($externalUserIds)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getStudentForms()
    {
        return [];
    }

    /* Getters/Setters
      */

    /**
     * @param Group $groupHandler
     */
    public function setGroup($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * @return Group
     */
    public function getGroup()
    {
        return $this->groupHandler;
    }
}
