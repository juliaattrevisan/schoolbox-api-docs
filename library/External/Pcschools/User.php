<?php
/**
 * PCSchools user connector
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\User;

class External_Pcschools_User implements External_UserInterface
{
    const CONTACT_TYPE_FATHER    = 'F';
    const CONTACT_TYPE_MOTHER    = 'M';
    const CONTACT_TYPE_GUARDIAN  = 'G';
    const CONTACT_TYPE_CAREGIVER = 'C';

    const CONTACT_LEVEL_FULL           = 'F';
    const CONTACT_LEVEL_NONE           = 'N';
    const CONTACT_LEVEL_NO_DEMOGRAPHIC = 'D';

    /**
     * @var MSSQL
     */
    private $database;

    /**
     * @var \Schoolbox\UserMapper
     */
    private $userMapper;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
//        // Required, as using @LOGIC_NO
//        $this->getDatabase()->query('SET ANSI_PADDING ON');
//        $this->getDatabase()->query('SET ANSI_WARNINGS ON');
        $this->userMapper = new \Schoolbox\UserMapper();
    }

    /**
     * Get student info for $externalUserId
     * StudentID,
     * StudentGiven1,
     * StudentSurname,
     * StudentBirthDate, (a unix timestamp)
     * StudentHouseDescription,
     * StudentHouse,
     * HeadOfHouseID,
     * StudentHeadOfHouse,
     * StudentBoarder
     * StudentForm,
     * FormDescription,
     * StudentFormStaffName,
     * StudentTutor,
     * StudentTutorName,
     * StudentTutorDescription,
     * TeacherFormID,
     * TeacherTutorID,
     * StudentYearLevel,
     * YearLevelCoordID,
     * StudentYearLevelCoordinator,
     * StudentLockerBook,
     * StudentEmergencyName,
     * StudentEmergencyPhone,
     * StudentEmergencyPhoneAlt,
     * StudentEmergency2Name,
     * StudentEmergency2Phone,
     * StudentEmergency2PhoneAlt,
     * StudentEmergency3Name,
     * StudentEmergency3Phone,
     * StudentEmergency3PhoneAlt,
     * StudentMedicalAlert,
     * StudentSpecialNeedsFlag,
     * StudentStudiesCode,
     * AlertMessage,
     *
     * @importance high - used on student profile page
     *
     * @param $externalUserId
     *
     * @return array
     */
    public function getStudent($externalUserId)
    {
        $row = $this->getDatabase()->getRow("
            SELECT
                S.STUDKEY AS StudentID,
                S.GIVEN_NAME AS StudentGiven1,
                S.LEGAL_SURNAME AS StudentSurname,
                S.BIRTH_DATE AS StudentBirthDate,
                S_HOUSE.DESCRIPTION AS StudentHouseDescription,
                S.HOUSE AS StudentHouse,
                '' AS HeadOfHouseID,
                '' AS StudentHeadOfHouse,
                S.STATUS AS StudentBoarder,
                '' AS Comment,
                S.HOME_CLASS AS StudentForm,
                S_CLASS.DESCRIPTION AS FormDescription,
                S_HOME_TEACHER.NAME_FOR_REPORT AS StudentFormStaffName,
                S.DEAN AS StudentTutor,
                S_HOME_TEACHER.NAME_FOR_REPORT AS StudentTutorName,
                S_DEAN.NAME_FOR_REPORT AS StudentTutorDescription,
                S.HOME_TEACHER AS TeacherFormID,
                S.DEAN AS TeacherTutorID,
                S.CURRENT_YEAR AS StudentYearLevel,
                S.DEAN AS YearLevelCoordID,
                '' AS StudentYearLevelCoordinator,
                S.LOCKER AS StudentLockerBook,
                S.EMERG_CONTACT AS StudentEmergencyName,
                S.EMERG_PHONE AS StudentEmergencyPhone,
                '' AS StudentEmergencyPhoneAlt,
                S_FAMILY.DAD_FIRSTNAME + ' ' + S_FAMILY.DAD_SURNAME AS StudentEmergency2Name,
                S_FAMILY.DAD_EMERG AS StudentEmergency2Phone,
                S_FAMILY.DAD_EMERG_PH AS StudentEmergency2PhoneAlt,
                S_FAMILY.MUM_FIRSTNAME + ' ' + S_FAMILY.MUM_SURNAME AS StudentEmergency3Name,
                S_FAMILY.MUM_EMERG AS StudentEmergency3Phone,
                S_FAMILY.MUM_EMERG_PH AS StudentEmergency3PhoneAlt,
                S.ALERT AS StudentMedicalAlert,
                '' AS StudentSpecialNeedsFlag,
                '' AS StudentStudiesCode,
                S.ALERT AS AlertMessage,
                0 AS countIndividualEducationPlans
            FROM STUDENT S
            LEFT JOIN CODEMAST AS S_HOUSE ON S.HOUSE = S_HOUSE.CODE AND S_HOUSE.TYPE = 'HOUSE'
            LEFT JOIN CODEMAST AS S_CLASS ON S.HOME_CLASS = S_CLASS.CODE AND S_CLASS.TYPE = 'CLASS'
            LEFT JOIN TEACHER AS S_HOME_TEACHER ON S.HOME_TEACHER = S_HOME_TEACHER.TEACHER_CODE
            LEFT JOIN TEACHER AS S_DEAN ON S.DEAN = S_DEAN.TEACHER_CODE
            LEFT JOIN FAMILY AS S_FAMILY ON S.FAMILY_CODE = S_FAMILY.FAMKEY
            WHERE S.STUDKEY = {$this->getDatabase()->smartQuote($externalUserId)}
        ");
        if (!empty($row)) {
            $row = $this->getDatabase()->trimRow($row);
            // Get boarder codes and munge student boarder status in
            $boarderCodes = array_map(
                'trim',
                explode(',', $this->getDatabase()->getOne('SELECT BOARDERCODES FROM SYSFILE'))
            );
            $row['StudentBoarder'] = in_array($row['StudentBoarder'], $boarderCodes);
        }
        return $row;
    }

    /**
     * Get a list of student contacts (parents) and their contact information
     * for the student $externalUserId
     * StudentContactID,
     * StudentContactTitle,
     * StudentContactPreferred,
     * StudentContactSurname,
     * StudentContactDefaultEmail,
     * ValidAddressFlag,
     * StudentContactAddress1,
     * StudentContactAddress2,
     * StudentContactAddress3,
     * StudentContactSuburb,
     * StudentContactPostCode,
     * StudentContactState,
     * StudentContactPhoneActual,
     * StudentContactSilentPhoneFlag,
     * StudentContactMobilePhone,
     * StudentContactOccupPhone,
     * StudentContactRelation,
     * StudentContactSpouseID,
     * StudentContactSpouseTitle,
     * StudentContactSpousePreferred,
     * StudentContactSpouseSurname,
     * StudentContactSpouseMobilePhone,
     * StudentContactSpouseOccupPhone,
     * StudentContactSpouseDefaultEmail,
     * StudentContactType,
     * StudentContactSpouseRelation
     *
     * @importance high - used on student profile page, and the report card.
     *
     * @param string $externalUserId
     *
     * @return array
     */
    public function getStudentContactList($externalUserId)
    {
        // Split contacts into separate father/mother/guardian/carer records
        // (at this stage, all with the same contact ID)
        $rowset = $this->getDatabase()->getAll("
            SELECT
                F.IDENTITY_ID#,
                -- Father
                F.DAD_LMS,
                F.FATHER_TITLE,
                F.DAD_FIRSTNAME,
                F.DAD_SURNAME,
                F.DAD_EMAIL,
                F.DAD_HOME_PHONE,
                F.DAD_MOBILE,
                F.DAD_BUSS_PHONE,
                F.DAD_STATUS,
                -- Mother
                F.MUM_LMS,
                F.MOTHER_TITLE,
                F.MUM_FIRSTNAME,
                F.MUM_SURNAME,
                F.MUM_EMAIL,
                F.MUM_HOME_PHONE,
                F.MUM_MOBILE,
                F.MUM_BUSS_PHONE,
                F.MUM_STATUS,
                -- Guardian
                F.GUARD_LMS,
                F.GUARDIAN_TITLE,
                F.GUARD_FIRSTNAME,
                F.GUARD_SURNAME,
                F.GUARD_EMAIL,
                F.GUARD_HOME_PH,
                F.GUARD_MOBILE,
                F.GUARD_BUSS_PH,
                F.GUARD_STATUS,
                -- Carer
                F.CGIVE_LMS,
                F.CGIVE_TITLE,
                F.CGIVE_FIRSTNAME,
                F.CGIVE_SURNAME,
                F.CGIVE_EMAIL,
                F.CGIVE_HOME_PH,
                F.CGIVE_MOBILE,
                F.CGIVE_BUSS_PH,
                F.CGIVE_STATUS
            FROM FAMILY AS F
            INNER JOIN STUDENT AS S ON F.FAMKEY = S.FAMILY_CODE
            WHERE S.STUDKEY = {$this->getDatabase()->smartQuote($externalUserId)}
        ");

        $base   = [
            'StudentContactID'                 => '',
            'StudentContactTitle'              => '',
            'StudentContactPreferred'          => '',
            'StudentContactSurname'            => '',
            'StudentContactDefaultEmail'       => '',
            'StudentContactPhoneActual'        => '',
            'StudentContactSilentPhoneFlag'    => '',
            'StudentContactMobilePhone'        => '',
            'StudentContactOccupPhone'         => '',
            'StudentContactRelation'           => '',
            'StudentContactSpouseID'           => '',
            'StudentContactSpouseTitle'        => '',
            'StudentContactSpousePreferred'    => '',
            'StudentContactSpouseSurname'      => '',
            'StudentContactSpouseMobilePhone'  => '',
            'StudentContactSpouseOccupPhone'   => '',
            'StudentContactSpouseDefaultEmail' => '',
            'StudentContactType'               => '',
            'StudentContactSpouseRelation'     => '',
        ];
        $result = [];
        foreach ($rowset as $row) {
            $result = array_merge(
                $result,
                $this->buildParentRecords($base, $this->getDatabase()->trimRow($row))
            );
        }
        return $result;
    }

    /**
     * Gets a list of Contacts for all Students in a particular year level.
     * The year level is stored in the external db.
     * Used in reporting.
     * StudentID,
     * StudentGiven1,
     * StudentSurname,
     * StudentYearLevel,
     * StudentHouse,
     * StudentBoarder,
     * StudentForm,
     * StudentContactID,
     * StudentContactTitle,
     * StudentContactPreferred,
     * StudentContactLegalFullName,
     * StudentContactSurname,
     * StudentContactDefaultEmail,
     * ValidAddressFlag,
     * StudentContactAddress1,
     * StudentContactAddress2,
     * StudentContactAddress3,
     * StudentContactSuburb,
     * StudentContactPostCode,
     * StudentContactState,
     * StudentContactCountryCode,
     * StudentContactHomeAddress1,
     * StudentContactHomeAddress2,
     * StudentContactHomeAddress3,
     * StudentContactHomeSuburb,
     * StudentContactHomePostCode,
     * StudentContactHomeState,
     * StudentContactHomeCountryCode,
     * StudentContactPhoneActual,
     * StudentContactMobilePhone,
     * StudentContactOccupPhone,
     * StudentContactSpouseID,
     * StudentContactSpouseTitle,
     * StudentContactSpousePreferred,
     * StudentContactSpouseLegalFullName,
     * StudentContactSpouseSurname,
     * StudentContactSpouseMobilePhone,
     * StudentContactSpouseOccupPhone,
     * StudentContactSpouseDefaultEmail,
     * StudentContactType,
     * StudentContactSilentPhoneFlag,
     * PrimaryDirectoryIncludeFlag,
     * SpouseDirectoryIncludeFlag,
     * StudentParentsSeparatedFlag
     *
     * @importance low - used only on the parent report.
     *
     * @param $yearlevel
     * @param $name
     * @param $boardingStatus
     * @param $form
     *
     * @return array
     */
    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form)
    {
        // Not required for MVP
        return [];
//        $query = "
//            SELECT
//                -- Student fields
//                S.STUDENT# AS StudentID,
//                S.GIVEN_NAME AS StudentGiven1,
//                S.LEGAL_SURNAME AS StudentSurname,
//                S.CURRENT_YEAR AS StudentYearLevel,
//                S.HOUSE AS StudentHouse,
//                S.STATUS AS StudentBoarder,
//                S.HOME_CLASS AS StudentForm,
//                -- Address
//                F.COR_ADDRESS,
//                F.COR_ADDRESS1,
//                F.COR_TOWN,
//                F.COR_POST_CODE,
//                F.STATE_COR,
//                -- Father
//                F.FATHER_TITLE,
//                F.DAD_FIRSTNAME,
//                F.DAD_SURNAME,
//                F.DAD_EMAIL,
//                F.DAD_HOME_PHONE,
//                F.DAD_MOBILE,
//                F.DAD_BUSS_PHONE,
//                F.DAD_STATUS,
//                -- Mother
//                F.MOTHER_TITLE,
//                F.MUM_FIRSTNAME,
//                F.MUM_SURNAME,
//                F.MUM_EMAIL,
//                F.MUM_HOME_PHONE,
//                F.MUM_MOBILE,
//                F.MUM_BUSS_PHONE,
//                F.MUM_STATUS,
//                -- Guardian
//                F.GUARDIAN_TITLE,
//                F.GUARD_FIRSTNAME,
//                F.GUARD_SURNAME,
//                F.GUARD_EMAIL,
//                F.GUARD_HOME_PH,
//                F.GUARD_MOBILE,
//                F.GUARD_BUSS_PH,
//                F.GUARD_STATUS,
//                -- Carer
//                F.CGIVE_TITLE,
//                F.CGIVE_FIRSTNAME,
//                F.CGIVE_SURNAME,
//                F.CGIVE_EMAIL,
//                F.CGIVE_HOME_PH,
//                F.CGIVE_MOBILE,
//                F.CGIVE_BUSS_PH,
//                F.CGIVE_STATUS
//            FROM STUDENT S
//            LEFT JOIN FAMILY AS S_FAMILY ON S.FAMILY_CODE = S_FAMILY.FAMKEY
//        ";
//
//        $whereParts = [];
//        if (strlen($yearlevel)) {
//            $whereParts[] = "S.CURRENT_YEAR = {$this->getDatabase()->smartQuote($yearlevel)}";
//        }
//        if (strlen($name)) {
//            $name         = $this->getDatabase()->quote($name);
//            $whereParts[] = "(S.GIVEN_NAME LIKE '%$name%' OR S.LEGAL_SURNAME LIKE '%$name%')";
//        }
//        if (strlen($form)) {
//            $whereParts[] = "S.HOME_CLASS = {$this->getDatabase()->smartQuote($yearlevel)}";
//        }
    }

    /**
     * Gets some basic information about a student
     * StudentID,
     * NetworkLogin,
     * StudentTitle,
     * StudentPreferred,
     * StudentSurname,
     * StudentGender,
     * StudentEmail,
     * StudentOccupEmail,
     * StudentYearLevel,
     * StudentHouse,
     * StudentBoarder,
     * StudentBoardingHouse,
     * StudentCampus
     *
     * @importance high - used in sync scripts.
     *
     * @return array
     */
    public function getStudentUserAccountInfo()
    {
        // "Null" date: 1/1/1753
        // To reformat boarder status or not?
        return $this->getDatabase()->trimAll(
            $this->getDatabase()->getAll("
                SELECT
                    S.STUDKEY AS StudentID,
                    ID.LOGIN AS NetworkLogin,
                    S.GIVEN_NAME AS StudentPreferred,
                    S.LEGAL_SURNAME AS StudentSurname,
                    S.SEX AS StudentGender,
                    S.EMAIL AS StudentEmail,
                    '' AS StudentOccupEmail,
                    S.CURRENT_YEAR AS StudentYearLevel,
                    S.HOUSE AS StudentHouse,
                    S.STATUS AS StudentBoarder,
                    '' AS StudentBoardingHouse,
                    S.CAMPUS AS StudentCampus
                FROM STUDENT S
                LEFT JOIN [IDENTITY] AS ID ON S.STUDENT# = ID.MEMBER#
                WHERE DATEDIFF(DAY, S.DATE_DEPART, '1753-01-01') = 0
            ")
        );
    }

    /**
     * Returns a list of email parent email addresseses for students.
     *
     * @importance medium - used in the class list to create a list of emails, and the report card.
     *
     * @param array $synergyIds
     *
     * @return array
     */
    public function getParentEmails($synergyIds)
    {
        if (empty($synergyIds)) {
            return [];
        }
        $query = "
            SELECT
                F.IDENTITY_ID#,
                -- Father
                F.DAD_LMS,
                F.DAD_EMAIL,
                -- Mother
                F.MUM_LMS,
                F.MUM_EMAIL,
                -- Guardian
                F.GUARD_LMS,
                F.GUARD_EMAIL,
                -- Carer
                F.CGIVE_LMS,
                F.CGIVE_EMAIL
            FROM FAMILY AS F
            INNER JOIN STUDENT AS S ON F.FAMKEY = S.FAMILY_CODE
        ";
        $whereParts = [];
        foreach ($synergyIds as $id) {
            $whereParts[] = "S.STUDKEY = {$this->getDatabase()->smartQuote($id)}";
        }
        $query .= ' WHERE '.implode(' OR ', $whereParts);

        // Munge data in same way we would in getStudentContactList
        $results = [];
        foreach ($this->getDatabase()->getAll($query) as $row) {
            $row = $this->getDatabase()->trimRow($row);
            $id  = $row['IDENTITY_ID#'];
            // For now, I'm going to assume that parent IDs will be suffixed
            if ($row['DAD_LMS'] === self::CONTACT_LEVEL_FULL) {
                $results[$id.'-'.self::CONTACT_TYPE_FATHER] = $row['DAD_EMAIL'];
            }
            if ($row['MUM_LMS'] === self::CONTACT_LEVEL_FULL) {
                $results[$id.'-'.self::CONTACT_TYPE_MOTHER] = $row['MUM_EMAIL'];
            }
            if ($row['GUARD_LMS'] === self::CONTACT_LEVEL_FULL) {
                $results[$id.'-'.self::CONTACT_TYPE_GUARDIAN] = $row['GUARD_EMAIL'];
            }
            if ($row['CGIVE_LMS'] === self::CONTACT_LEVEL_FULL) {
                $results[$id.'-'.self::CONTACT_TYPE_CAREGIVER] = $row['CGIVE_EMAIL'];
            }
        }
        return $results;
    }

    /**
     * Gets the contact info of the person who is a Student's Contact.
     * $externalId is that of the Contact, not the Student
     * Title,
     * Preferred,
     * Surname,
     * Email,
     * OccupEmail,
     * DefaultEmailCode,
     * HomePhone,
     * SilentPhoneFlag,
     * MobilePhone,
     * DirectoryIncludeFlag,
     * SpouseID,
     * SpouseTitle,
     * SpousePreferred,
     * SpouseSurname,
     * SpouseEmail,
     * SpouseOccupEmail,
     * SpouseDefaultEmailCode,
     * SpouseMobilePhone,
     * SpouseDirectoryIncludeFlag
     *
     * @importance medium - used on parent profile page.
     *
     * @param $externalUserId
     *
     * @return array
     */
    public function getParent($externalUserId)
    {
        // Non-core promise
        return [];
    }

    /**
     * Gets the contact info of the person who is a Student's Contact. The ID
     * passed is the $externalUserId of the Contact, not the Student. Anyone who
     * is not supposed to be having their details publicised (via the
     * DirectoryIncludeFlag) are not included in the result set.
     * I think this was used to only update parents of current Schoolbox
     * students, rather than updating everyone blindly
     * Accept array of Synergy IDs, or just an int
     *
     * @importance low - used in some sync scripts.
     *
     * @param $externalUserId
     *
     * @return array (
     *     ID,
     *     Title,
     *     Preferred,
     *     Surname,
     *     Email,
     *     OccupEmail,
     *     DefaultEmailCode,
     *     DirectoryIncludeFlag
     * )
     * or
     * @return array(
     *     array(
     *         ID,
     *         Title,
     *         Preferred,
     *         Surname,
     *         Email,
     *         OccupEmail,
     *         DefaultEmailCode,
     *         DirectoryIncludeFlag
     *     )
     * )
     */
    public function getParentSync($externalUserId)
    {
        // Non-core promise
        return [];
    }

    /**
     * Get the following fields for all parents
     * (yes they're prefixed as Student-)
     * Used in Synchronise
     * StudentContactNetworkLogin,
     * StudentContactID,
     * StudentContactTitle,
     * StudentContactPreferred,
     * StudentContactSurname,
     * StudentContactDefaultEmail,
     * StudentContactSpouseNetworkLogin,
     * StudentContactSpouseID,
     * StudentContactSpouseTitle,
     * StudentContactSpousePreferred,
     * StudentContactSpouseSurname,
     * StudentContactSpouseDefaultEmail,
     * StudentYearLevel,
     * StudentCampus,
     * StudentGender
     *
     * @importance high - used in sync script.
     *
     * @return array
     */
    public function getParentUserAccountInfo()
    {
        $result = [];
        $rowset = $this->getDatabase()->trimAll(
            $this->getDatabase()->getAll("
                SELECT
                    F.IDENTITY_ID#,
                    ID.LOGIN,
                    -- Father
                    F.DAD_LMS,
                    F.FATHER_TITLE,
                    F.DAD_FIRSTNAME,
                    F.DAD_SURNAME,
                    F.DAD_EMAIL,
                    -- Mother
                    F.MUM_LMS,
                    F.MOTHER_TITLE,
                    F.MUM_FIRSTNAME,
                    F.MUM_SURNAME,
                    F.MUM_EMAIL,
                    -- Guardian
                    F.GUARD_LMS,
                    F.GUARDIAN_TITLE,
                    F.GUARD_FIRSTNAME,
                    F.GUARD_SURNAME,
                    F.GUARD_EMAIL,
                    -- Carer
                    F.CGIVE_LMS,
                    F.CGIVE_TITLE,
                    F.CGIVE_FIRSTNAME,
                    F.CGIVE_SURNAME,
                    F.CGIVE_EMAIL,
                    -- Student
                    S.CURRENT_YEAR AS StudentYearLevel,
                    S.CAMPUS AS StudentCampus,
                    S.SEX AS StudentGender
                FROM FAMILY AS F
                LEFT JOIN [IDENTITY] AS ID ON F.IDENTITY_ID# = ID.MEMBER#
                INNER JOIN STUDENT AS S ON F.FAMKEY = S.FAMILY_CODE
                WHERE DATEDIFF(DAY, S.DATE_DEPART, '1753-01-01') = 0
            ")
        );
        foreach ($rowset as $row) {
            $student = [
                'StudentYearLevel' => $row['StudentYearLevel'],
                'StudentCampus'    => $row['StudentCampus'],
                'StudentGender'    => $row['StudentGender'],
            ];
            $result = array_merge(
                $result,
                $this->buildParentRecords($student, $row)
            );
        }
        return $result;
    }

    /**
     * Get the following fields for the staff member $externalUserId
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
     * @importance high - used on staff profile page.
     *
     * @param $externalUserId
     *
     * @return array
     */
    public function getStaff($externalUserId)
    {
        return $this->getDatabase()->trimRow(
            $this->getDatabase()->getRow("
                SELECT
                    T.TEACHER_CODE AS SchoolStaffCode,
                    '' AS StaffBuilding,
                    T.STAFF_ROOM AS StaffRoom,
                    T.INTERNAL_PH_EXT AS StaffExtension,
                    T.MOBILE AS StaffMobilePhone,
                    T.MOBILE AS StaffDefaultMobilePhone,
                    T_CAMPUS.DESCRIPTION AS StaffCampusDescription,
                    T.STATUS AS StaffCategoryDescription,
                    '' AS StaffDepartment,
                    '' AS StaffVoiceMail
                FROM TEACHER T
                LEFT JOIN CODEMAST AS T_CAMPUS ON T.CAMPUS = T_CAMPUS.CODE AND T_CAMPUS.TYPE = 'CAMPUS'
                WHERE T.TEACHER_CODE = {$this->getDatabase()->smartQuote($externalUserId)}
            ")
        );
    }

    /**
     * Get staff account information
     *
     * @importance high - used in sync scripts.
     *
     * @return array(
     *     array(
     *         StaffID,
     *         NetworkLogin,
     *         ActiveFlag,
     *         StaffTitle,
     *         StaffPreferredName,
     *         StaffSurname,
     *         DefaultEmailCode,
     *         OccupEmail,
     *         Email,
     *         StaffCampus,
     *         StaffCategoryType,
     *         StaffCategory,
     *         StaffDepartment
     *     )
     * )
     */
    public function getStaffUserAccountInfo()
    {
        return $this->getDatabase()->trimAll(
            $this->getDatabase()->getAll("
                SELECT
                    T.TEACHER# AS StaffID,
                    ID.LOGIN AS NetworkLogin,
                    '' AS ActiveFlag,
                    T.SALUTATION AS StaffTitle,
                    T.GIVEN_NAMES AS StaffPreferredName,
                    T.SURNAME AS StaffSurname,
                    '' AS DefaultEmailCode,
                    '' AS OccupEmail,
                    T.EMAIL AS Email,
                    T.CAMPUS AS StaffCampus,
                    '' AS StaffCategoryType,
                    T.STATUS AS StaffCategory,
                    '' AS StaffDepartment
                FROM TEACHER T
                LEFT JOIN [IDENTITY] AS ID ON T.TEACHER# = ID.MEMBER#
                LEFT JOIN CODEMAST AS T_CAMPUS ON T.CAMPUS = T_CAMPUS.CODE AND T_CAMPUS.TYPE = 'CAMPUS'
                WHERE DATEDIFF(DAY, T.DATE_LEFT, '1753-01-01') = 0
            ")
        );
    }

    /**
     * Get contact list for staff directory
     * return array(
     *     array(
     *         StaffID,
     *         NetworkLogin,
     *         ActiveFlag,
     *         StaffTitle,
     *         StaffPreferredName,
     *         StaffSurname,
     *         DefaultEmailCode,
     *         OccupEmail,
     *         Email,
     *         StaffCampus,
     *         StaffCategoryType,
     *         StaffCategory,
     *         StaffDepartment
     *     )
     * )
     *
     * @importance low - used on staff directory only.
     *
     * @return array
     */
    public function getStaffContactListExtended()
    {
        // Non-core promise
        return [];
    }

    /**
     * Gets the current semester for the user.
     *
     * @importance low - used everywhere, but if you can't get it from the DB,
     *             Jan-Jun: 1, Jul-Dec: 2
     *
     * @param string $externalUserId
     *
     * @return string The current semester
     */
    public function getCurrentSemester($externalUserId = null)
    {
        return date('m') < 6 ? 1 : 2;
    }

    /**
     * Gets the current year for the user.
     * Needs a method because a user could be set on file as being on a
     * previous year.
     *
     * @importance low - used everywhere, but if you can't get it from the DB,
     *             return the current year.
     *
     * @param string $externalUserId
     *
     * @return string The current file year for the user
     */
    public function getCurrentYear($externalUserId = null)
    {
        return date('Y');
    }

    /**
     * Get a list of subjects for $externalUserId
     * $externalUserId and $userId are properties of the same person
     * This method internally distinguishes between students and
     * staff by using the Schoolbox $userId
     * returns array(
     *     array(
     *         'code' => class category code
     *         'name' => class name
     *         'classType' => a string e.g. academic, music, maths
     *         'campusCode' => the campus this class is in
     *     )
     *     ...
     * )
     *
     * @importance high - used on student profile page, and for determining a
     *             student's memberships.
     *
     * @param User $user
     *
     * @return array
     */
    public function getSubjectList(User $user)
    {
        $externalId = $user->getExternalId();

        if (empty($externalId)) {
            return [];
        }

        $studentExternalId = '';
        $teacherExternalId = '';
        $timePeriods       = [];
        $classes           = [];
        foreach ($user->getCampusCollection() as $campus) {
            $timePeriods[$campus->getExtTimetableCode()] = $campus->getExtTimetableCode();
        }
        foreach ($timePeriods as $timePeriod) {
            if ($user->getRole()->isStudent()) {
                $studentExternalId = $externalId;
            } elseif ($user->getRole()->isStaff()) {
                $teacherExternalId = $externalId;
            } else {
                return [];
            }

            // Ordering of result sets from spSelTimetable:
            // Day periods
            // Day classes
            // Home classes
            // Week days
            // Terms
            $statement = $this->getDatabase()->prepareProcedure('spSelTimetable');
            $statement->bindInputs([
                'DATE' => date('Ymd'),
                'TIMEPERIOD' => $timePeriod,
                'STUDKEY' => $studentExternalId,
                'TEACHERCODE' => $teacherExternalId,
            ]);

            $statement->execute();

            // Need only day classes
            $statement->nextRowset();

            // Add classes for each day into it
            /** @noinspection PhpAssignmentInConditionInspection */
            while ($dayClass = $statement->fetch()) {
                $code = $timePeriod . '/' . $dayClass['YEAR'] . $dayClass['ABBREV'] . $dayClass['CLASS'];
                $classes[$code] = [
                    'code'       => $code,
                    'name'       => $dayClass['DESC'],
                    'classType'  => '',
                    'campusCode' => $timePeriod,
                ];
            }
            $statement->close();
        }
        return array_values($classes);
    }

    /**
     * Get all the enrolled students belonging to $externalUserId
     * (usually a parent)
     * Returns an array of $externalId
     *
     * @importance high - used to determine a parent's children, and related permissions.
     *
     * @param $externalUserId
     *
     * @return array
     */
    public function getEnrolledStudents($externalUserId)
    {
        // parent external ID format: $famKey-{some code}
        $suffix     = '';
        $familyCode = $externalUserId;
        $parts = explode('-', $externalUserId);
        if (count($parts) > 1) {
            $suffix     = array_pop($parts);
            $familyCode = implode('-', $parts);
        }
        $query = "
            SELECT S.STUDKEY AS RelatedID
            FROM STUDENT S
            INNER JOIN FAMILY AS S_FAMILY ON S.FAMILY_CODE = S_FAMILY.FAMKEY
            WHERE S_FAMILY.IDENTITY_ID# = {$this->getDatabase()->smartQuote($familyCode)}
        ";
        $quotedContactLevel = $this->getDatabase()->smartQuote(self::CONTACT_LEVEL_FULL);
        switch ($suffix) {
            case 'F':
                $query .= " AND S_FAMILY.DAD_LMS = $quotedContactLevel";
                break;
            case 'M':
                $query .= " AND S_FAMILY.MUM_LMS = $quotedContactLevel";
                break;
            case 'C':
                $query .= " AND S_FAMILY.CGIVE_LMS = $quotedContactLevel";
                break;
            case 'G':
                $query .= " AND S_FAMILY.GUARD_LMS = $quotedContactLevel";
                break;
            default:
                break;
        }
        return $this->getDatabase()->trimAll($this->getDatabase()->getAll($query));
    }

    /**
     * Get the email address as a string for a particular person
     *
     * @importance low - I'm not sure this is used anywhere yet?
     *
     * @param $externalUserId
     *
     * @return string
     */
    public function getEmail($externalUserId)
    {
        // non-core promise
        return '';
    }

    /**
     * Get the portrait for a particular user
     * Returns a local file path to the user's portrait
     *
     * @importance low - most schools manually upload portraits to `config.portrait_dir`.
     *
     * @param $externalUserId
     *
     * @return string
     */
    public function getPortrait($externalUserId)
    {
        // Copied from External_Schoolbox_User strategy
        $portraitPath = realpath(Config::get('portrait_dir')) . "/$externalUserId.jpg";
        if (is_readable($portraitPath)) {
            return $portraitPath;
        } else {
            return false;
        }
    }

    /**
     * Determine whether someone is a day boarder or not
     *
     * @importance low - used on profile page, but only to show if student is a boarder
     *
     * @param $boarderValue
     *
     * @return string
     */
    public function determineBoarderType($boarderValue)
    {
        // Non-core promise
        return '';
//        if (!strlen($boarderValue)) {
//            return '';
//        } else {
//            $boarders = explode(',', $this->getDatabase()->getOne('SELECT BOARDERCODES FROM SYSFILE'));
//            if (in_array($boarderValue, $boarders)) {
//                return 'Boarder';
//            }
//        }
//        return 'Day';
    }

    /**
     * Find the housemasterId for a particular house
     *
     * @importance low - I'm not sure this is used anywhere yet?
     *
     * @param $houseID
     *
     * @return string
     */
    public function getHousemasterByHouseId($houseID)
    {
        // Non-core promise
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
     * List of campuses a staff member is involved in based on timetable
     *
     * @importance low - used in a few sync scripts.
     *
     * @param int $staffID
     *
     * @return array|bool
     */
    public function getCampusFromTimetable($staffID)
    {
        // Non-core promise
        return [];
    }

    /**
     * Retrieves a list of forms that a student may be in.
     *
     * @importance low - used on student report only.
     * @return array [['Code' => string, 'Description' => string], ...]
     */
    public function getStudentForms()
    {
        // Non-core promise
        return [];
    }

    // Local methods

    /**
     * @return MSSQL
     */
    private function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param  MSSQL $database
     *
     * @return $this
     */
    private function setDatabase($database)
    {
        $this->database = $database;
        return $this;
    }

    /**
     * Builds a parent record list from a row from `getStudentContactList()` or
     * other method that returns a list.
     *
     * @param array $base
     * @param array $row
     *
     * @return array
     */
    private function buildParentRecords($base, $row)
    {
        $results = array_filter([
            $this->buildSingleParentRecord($row, self::CONTACT_TYPE_FATHER),
            $this->buildSingleParentRecord($row, self::CONTACT_TYPE_MOTHER),
            $this->buildSingleParentRecord($row, self::CONTACT_TYPE_GUARDIAN),
            $this->buildSingleParentRecord($row, self::CONTACT_TYPE_CAREGIVER),
        ]);
        foreach ($results as &$result) {
            $result = array_merge($base, $result);
        }
        unset($result);
        return $results;
    }

    /**
     * Builds an individual parent record
     *
     * @param array  $row
     * @param string $type
     *
     * @return array
     */
    private function buildSingleParentRecord($row, $type)
    {
        switch ($type) {
            case self::CONTACT_TYPE_FATHER:
                $mainPrefix  = 'DAD';
                $titlePrefix = 'FATHER';
                $relation    = 'Father';
                break;
            case self::CONTACT_TYPE_MOTHER:
                $mainPrefix  = 'MUM';
                $titlePrefix = 'MOTHER';
                $relation    = 'Mother';
                break;
            case self::CONTACT_TYPE_CAREGIVER:
                $mainPrefix  = 'CGIVE';
                $titlePrefix = 'CGIVE';
                $relation    = 'Caregiver';
                break;
            case self::CONTACT_TYPE_GUARDIAN:
                $mainPrefix  = 'GUARD';
                $titlePrefix = 'GUARDIAN';
                $relation    = 'Guardian';
                break;
            default:
                return [];
        }

        // Assumption: if $mainPrefix.'_LMS' isn't full, don't build this record
        if ($row[$mainPrefix.'_LMS'] !== self::CONTACT_LEVEL_FULL) {
            return [];
        }

        // Field mappings
        $fieldMappings = [
            'StudentContactID'           => 'IDENTITY_ID#',
            'StudentContactTitle'        => $titlePrefix . '_TITLE',
            'StudentContactPreferred'    => $mainPrefix . '_FIRSTNAME',
            'StudentContactSurname'      => $mainPrefix . '_SURNAME',
            'StudentContactDefaultEmail' => $mainPrefix . '_EMAIL',
            'StudentContactAddress1'     => 'COR_ADDRESS',
            'StudentContactAddress2'     => 'COR_ADDRESS1',
            'StudentContactSuburb'       => 'COR_TOWN',
            'StudentContactPostCode'     => 'COR_POST_CODE',
            'StudentContactState'        => 'STATE_COR',
            'StudentContactPhoneActual'  => $mainPrefix . '_HOME_PHONE',
            'StudentContactMobilePhone'  => $mainPrefix . '_MOBILE',
            'StudentContactOccupPhone'   => $mainPrefix . '_BUSS_PHONE',
            'StudentContactRelation'     => $mainPrefix . '_STATUS',
            'StudentYearLevel'           => 'StudentYearLevel',
            'StudentCampus'              => 'StudentCampus',
            'StudentGender'              => 'StudentGender',
        ];
        $result = [];
        foreach ($fieldMappings as $to => $from) {
            if (array_key_exists($from, $row)) {
                $result[$to] = $row[$from];
            }
        }

        // Suffix contact ID with type
        $result['StudentContactID'] .= '-'.$type;

        // Build fullname too if I can
        if (array_key_exists($mainPrefix.'_FIRSTNAME', $row) || array_key_exists($mainPrefix.'_SURNAME', $row)) {
            $result['StudentContactLegalFullName']
                = $row[$mainPrefix.'_FIRSTNAME'] . ' ' . $row[$mainPrefix.'_SURNAME'];
        }
        // Build contact relation default value
        if (array_key_exists('StudentContactRelation', $result)) {
            $result['StudentContactRelation'] = $result['StudentContactRelation'] ?: $relation;
        }
        return $result;
    }
}
