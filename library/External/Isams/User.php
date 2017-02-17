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
    /**
     * @var MSSQL
     */
    private $database;

    /** @var Group */
    private $groupHandler;

    /** Constructor */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        $this->setGroup(new Group());
    }

    /**
     * Retrieves the user's Doublefirst image
     *
     * @param Integer $externalUserId
     * @return Bool|String
     */
    public function getPortrait($externalUserId)
    {
        return false;
    }

    /**
     * Gets the parents of student
     * @param $externalUserId
     * @return mixed
     */
    public function getParent($externalUserId)
    {
        $externalUserId = $this->database->quote($externalUserId);

        $query = "
            SELECT      V_ParentMother.ParentID             AS ID,
                        ''                                  AS Title,
                        V_ParentMother.ParentFirstname      AS Preferred,
                        V_ParentMother.ParentSurname        AS Surname,
                        V_ParentMother.CISEmail             AS Email,
                        ''                                  AS OccupEmail,
                        ''                                  AS DefaultEmailCode,
                        ''                                  AS HomePhone,
                        V_ParentMother.Mobile               AS MobilePhone,
                        0                                   AS SilentPhoneFlag,
                        0                                   AS SilentMobilePhoneFlag,
                        1                                   AS DirectoryIncludeFlag,
                        V_ParentFather.ParentID             AS SpouseID,
                        ''                                  AS SpouseTitle,
                        V_ParentFather.ParentFirstname      AS SpousePreferred,
                        V_ParentFather.ParentSurname        AS SpouseSurname,
                        V_ParentFather.CISEmail             AS SpouseEmail,
                        ''                                  AS SpouseOccupEmail,
                        ''                                  AS SpouseDefaultEmailCode,
                        V_ParentFather.Mobile               AS SpouseMobilePhone,
                        1                                   AS SpouseDirectoryIncludeFlag
            FROM        V_Student
            LEFT JOIN   V_Parent AS V_ParentMother ON V_ParentMother.StudentID = '{$externalUserId}' AND V_ParentMother.Relationship = 'Mother'
            LEFT JOIN   V_Parent AS V_ParentFather ON V_ParentFather.StudentID = '{$externalUserId}' AND V_ParentFather.Relationship = 'Father'
            WHERE       V_Student.StudentID = '{$externalUserId}'
        ";

        $parent = $this->getDatabase()->getRow($query);

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
        $emailList = array();
        $id_list = implode(',', array_map('intval', $synergyIds));

        if (empty($id_list)) {
            return array();
        }

        $query =
        "
            SELECT V_Parent.ParentID, V_Parent.CISEmail 
            FROM V_Parent
            WHERE V_Parent.StudentID IN ({$id_list})
            AND V_Parent.CISEmail <> ''
        ";

        $this->getDatabase()->query($query);

        while ($row = $this->getDatabase()->getNextArray()) {
            if (!isset($emailList[$row['ParentID']])) {
                $emailList[$row['ParentID']] = $row['CISEmail'];
            }
        }

        return $emailList;
    }

    /**
     * Get a students info from Doublefirst
     *
     * @param  $externalUserId
     * @return array|bool
     */
    public function getStudent($externalUserId)
    {
        $externalUserId = $this->database->quote($externalUserId);

        $query = "
            SELECT      StudentID                   AS StudentID,
                        StudentFirstname        AS StudentGiven1,
                        StudentSurname          AS StudentSurname,
                        DOB                     AS StudentBirthDate,                
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
                        ''                      AS StudentBoarder
            FROM        V_Student
            WHERE       StudentID = {$externalUserId}
        ";

        return $this->getDatabase()->getRow($query);
    }

    /**
     * Retrieve selected staff members details
     * @param $externalUserId
     * @return array|bool
     */
    public function getStaff($externalUserId)
    {
        $externalUserId = $this->database->quote($externalUserId);

        $staff = "
            SELECT          StaffID             AS SchoolStaffCode,
                            ''                  AS StaffBuilding,
                            ''                  AS StaffRoom,
                            ''                  AS StaffExtension,
                            ''                  AS StaffMobilePhone,
                            Campus              AS StaffCampusDescription,
                            DivisionID          AS StaffCategoryDescription,
                            ''                  AS StaffDepartment,
                            ''                  AS StaffVoiceMail
            FROM            V_Staff
            WHERE           StaffID = '{$externalUserId}'
        ";

        return $this->getDatabase()->getRow($staff);
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
                        V_Student.StudentID                 AS StudentID,
                        V_Student.StudentFirstName          AS StudentGiven1,
                        V_Student.StudentSurname            AS StudentSurname,
                        V_Student.Grade                     AS StudentYearLevel,
                        ''                                  AS StudentHouse,
                        ''                                  AS StudentBoarder,
                        ''                                  AS StudentForm,
                        V_ParentMother.ParentID             AS StudentContactID,
                        ''                                  AS StudentContactTitle,
                        V_ParentMother.ParentFirstname      AS StudentContactPreferred,
                        V_ParentMother.ParentFirstname      AS StudentContactLegalFullName,
                        V_ParentMother.ParentSurname        AS StudentContactSurname,
                        V_ParentMother.CISEmail             AS StudentContactDefaultEmail,
                        1                                   AS ValidAddressFlag,
                        V_ParentMother.Address1             AS StudentContactAddress1,
                        V_ParentMother.Address2             AS StudentContactAddress2,
                        V_ParentMother.Address3             AS StudentcontactAddress3,
                        ''                                  AS StudentContactSuburb,
                        V_ParentMother.PostCode             AS StudentContactPostCode,
                        ''                                  AS StudentContactState,
                        V_ParentMother.Country              AS StudentContactCountryCode,

                        V_ParentMother.Address1             AS StudentContactHomeAddress1,
                        V_ParentMother.Address2             AS StudentContactHomeAddress2,
                        V_ParentMother.Address3             AS StudentContactHomeAddress3,
                        ''                                  AS StudentContactHomeSuburb,
                        V_ParentMother.PostCode             AS StudentContactHomePostCode,
                        ''                                  AS StudentContactHomeState,
                        V_ParentMother.Country              AS StudentContactHomeCountryCode,
                        V_ParentMother.Mobile               AS StudentContactMobilePhone,
                        ''                                  AS StudentContactOccupPhone,
                        'Mother'                            AS StudentContactRelation,
                        

                        V_ParentFather.ParentID             AS StudentContactSpouseID,
                        ''                                  AS StudentContactSpouseTitle,
                        V_ParentFather.ParentFirstname      AS StudentContactSpousePreferred,
                        V_ParentFather.ParentSurname        AS StudentContactSpouseSurname,
                        V_ParentFather.Mobile               AS StudentContactSpouseMobilePhone,
                        ''                                  AS StudentContactSpouseOccupPhone, 
                        V_ParentFather.CISEmail             AS StudentContactSpouseDefaultEmail,

                        'SC1'                               AS StudentContactType,
                        0                                   AS StudentContactSilentPhoneFlag,
                        0                                   AS StudentContactSilentMobilePhoneFlag,
                        1                                   AS PrimaryDirectoryIncludeFlag,
                        1                                   AS SpouseDirectoryIncludeFlag,
                        0                                   AS SilentHomeAddressFlag,
                        0                                   AS StudentParentsSeparatedFlag

            FROM        V_Student
            LEFT JOIN   V_Parent AS V_ParentMother ON V_ParentMother.StudentID = V_Student.StudentID AND V_ParentMother.Relationship = 'Mother'
            LEFT JOIN   V_Parent AS V_ParentFather ON V_ParentFather.StudentID = V_Student.StudentID AND V_ParentFather.Relationship = 'Father'
        ";

        if (!empty($yearlevel)) {
            $yearlevel = $this->getDatabase()->quote($yearlevel);
            $query .= "WHERE V_Student.Grade = '{$yearlevel}'";
        }

        $query .= " ORDER BY StudentSurname, StudentGiven1, StudentID";

        return $this->getDatabase()->getAll($query) ?: array();
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
                        V_ParentMother.ParentID             AS StudentContactID,
                        ''                                  AS StudentContactTitle,
                        V_ParentMother.ParentFirstname      AS StudentContactPreferred,
                        V_ParentMother.ParentSurname        AS StudentContactSurname,
                        V_ParentMother.CISEmail             AS StudentContactDefaultEmail,
                        1                                   AS ValidAddressFlag,
                        V_ParentMother.Address1             AS StudentContactAddress1,
                        V_ParentMother.Address2             AS StudentContactAddress2,
                        V_ParentMother.Address3             AS StudentcontactAddress3,
                        ''                                  AS StudentContactSuburb,
                        V_ParentMother.PostCode             AS StudentContactPostCode,
                        ''                                  AS StudentContactState,
                        V_ParentMother.Country              AS StudentContactCountryCode,
                        V_ParentMother.Mobile               AS StudentContactMobilePhone,
                        ''                                  AS StudentContactOccupPhone,
                        ''                                  AS StudentContactPhoneActual,
                        'Mother'                            AS StudentContactRelation,
                        V_ParentFather.ParentID             AS StudentContactSpouseID,
                        ''                                  AS StudentContactSpouseTitle,
                        V_ParentFather.ParentFirstname      AS StudentContactSpousePreferred,
                        V_ParentFather.ParentSurname        AS StudentContactSpouseSurname,
                        V_ParentFather.Mobile               AS StudentContactSpouseMobilePhone,
                        ''                                  AS StudentContactSpouseOccupPhone, 
                        V_ParentFather.CISEmail             AS StudentContactSpouseDefaultEmail
            FROM        V_Student
            LEFT JOIN   V_Parent AS V_ParentMother ON V_ParentMother.StudentID = V_Student.StudentID AND V_ParentMother.Relationship = 'Mother'
            LEFT JOIN   V_Parent AS V_ParentFather ON V_ParentFather.StudentID = V_Student.StudentID AND V_ParentFather.Relationship = 'Father'
            WHERE       V_Student.StudentID = '{$externalUserId}'
        ";

        return $this->getDatabase()->getAll($query) ?: array();
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
        $externalId = $user->getExternalId();

        $sql =
            "
          SELECT DISTINCT   V_Class.ClassCode AS code
          FROM              V_Class
          WHERE             V_Class.StudentID = '{$externalId}'
        ";

        $this->getDatabase()->query($sql);
        $subjectList = array();

        while ($subject = $this->getDatabase()->getNextArray()) {
            $subjectList[] = array(
                'code' => $subject['code'],
                'name' => $subject['code'],
                'classType' => '',
                'campusCode' => $user->getCampusCollection()->first()->getExtTimetableCode()
            );
        }

        if (is_array($subjectList)) {
            return $subjectList;
        } else {
            return [];
        }
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
            SELECT    StudentID  
            FROM      V_Parent   
            WHERE     V_Parent.ParentID = '{$externalUserId}'
        ";

        return $this->getDatabase()->getAll($query);
    }

    /**
     * Retrieve email address
     * @param $id
     * @return array|bool
     */
    public function getEmail($id)
    {
        $externalUserId = $this->database->quote($id);

        $query =
            "
          SELECT  V_Parent.CISEmail 
          FROM    V_Parent 
          WHERE   V_Parent.ParentID = '{$externalUserId}'
        ";

        return $this->getDatabase()->getOne($query);
    }

    /**
     * Gets all Students' account info from Doublefirst
     *
     * @return array|bool
     */
    public function getStudentUserAccountInfo()
    {
        $externalUserId = $this->database->quote($externalUserId);

        $query = "
            SELECT      StudentID               AS StudentID,
                        ''                      AS NetworkLogin,
                        ''                      AS StudentTitle,
                        StudentFirstname        AS StudentPreferred,
                        StudentSurname          AS StudentSurname,
                        Gender                  AS StudentGender,
                        StudentEmail            AS StudentEmail,
                        ''                      AS StudentOccupEmail,
                        Grade                   AS StudentYearLevel,
                        ''                      AS StudentHouse,
                        ''                      AS StudentBoarder,
                        ''                      AS StudentBoardingHouse,
                        ''                      AS StudentCampus
            FROM        V_Student
            WHERE       StudentID = {$externalUserId}
        ";

        return $this->getDatabase()->getRow($query);
    }

    /**
     * Gets all Parents' account info from Doublefirst
     *
     * @return array|bool
     */
    public function getParentUserAccountInfo()
    {
        $query = "
            SELECT      
                        ''                                  AS StudentContactNetworkLogin,
                        V_ParentMother.ParentID             AS StudentContactID,
                        ''                                  AS StudentContactTitle,
                        V_ParentMother.ParentFirstname      AS StudentContactPreferred,
                        V_ParentMother.ParentSurname        AS StudentContactSurname,
                        V_ParentMother.CISEmail             AS StudentContactDefaultEmail,
                        ''                                  AS StudentContactSpouseNetworkLogin,
                        V_ParentFather.ParentID             AS StudentContactSpouseID,
                        ''                                  AS StudentContactSpouseTitle,
                        V_ParentFather.ParentFirstname      AS StudentContactSpousePreferred,
                        V_ParentFather.ParentSurname        AS StudentContactSpouseSurname,
                        V_ParentFather.CISEmail             AS StudentContactSpouseDefaultEmail,
                        V_Student.Grade                     AS StudentYearLevel,
                        ''                                  AS StudentCampus,
                        Gender                              AS StudentGender
            FROM        V_Student
            LEFT JOIN   V_Parent AS V_ParentMother ON V_ParentMother.StudentID = V_Student.StudentID AND V_ParentMother.Relationship = 'Mother'
            LEFT JOIN   V_Parent AS V_ParentFather ON V_ParentFather.StudentID = V_Student.StudentID AND V_ParentFather.Relationship = 'Father'
        ";

        return $this->getDatabase()->getAll($query) ?: array();

    }


    /**
     * Gets all active Staff's account info from Doublefirst
     *
     * @return array|bool
     */
    public function getStaffUserAccountInfo()
    {
        $query = "
            SELECT      StaffID           AS StaffID,
                        teacherusercode   AS NetworkLogin,
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

        return $this->getDatabase()->getAll($query);

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
                        teacherusercode   AS NetworkLogin,
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

        return $this->getDatabase()->getAll($query);
    }

    /**
     * List of campuses a staff member is involved in based on timetable. No such thing in Doublefirst.
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
     *
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
     * {@inheritdoc}
     */
    public function getStudentForms()
    {
        return [];
    }

    /* Getters/Setters
      */

    /**
     * Set database
     *
     * @param $database
     */
    private function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * Get database
     *
     * @return MSSQL
     */
    private function getDatabase()
    {
        return $this->database;
    }

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