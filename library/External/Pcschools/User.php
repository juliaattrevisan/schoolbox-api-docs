<?php
/**
 * PCSchools user connector
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Pcschools_User implements External_UserInterface
{
    /**
     * @var MSSQL
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
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
     * @param $externalUserId
     *
     * @return array
     */
    public function getStudent($externalUserId)
    {
        $row = $this->getDatabase()->getRow("
            SELECT
                S.STUDENT# AS StudentID,
                S.GIVEN_NAME AS StudentGiven1,
                S.LEGAL_SURNAME AS StudentSurname,
                S.BIRTH_DATE AS StudentBirthDate,
                S_HOUSE.DESCRIPTION AS StudentHouseDescription,
                S.HOUSE AS StudentHouse,
                '' AS HeadOfHouseID,
                '' AS StudentHeadOfHouse,
                S.STATUS AS StudentBoarder,
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
                S_FAMILY.DAD_FIRSTNAME | ' ' | S_FAMILY.DAD_SURNAME AS StudentEmergency2Name,
                S_FAMILY.DAD_EMERG AS StudentEmergency2Phone,
                S_FAMILY.DAD_EMERG_PH AS StudentEmergency2PhoneAlt,
                S_FAMILY.MUM_FIRSTNAME | ' ' | S_FAMILY.MUM_SURNAME AS StudentEmergency3Name,
                S_FAMILY.MUM_EMERG AS StudentEmergency3Phone,
                S_FAMILY.MUM_EMERG_PH AS StudentEmergency3PhoneAlt,
                S.ALERT AS StudentMedicalAlert,
                '' AS StudentSpecialNeedsFlag,
                '' AS StudentStudiesCode,
                S.ALERT AS AlertMessage
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
     * @param $externalUserId
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
                -- Address
                F.COR_ADDRESS,
                F.COR_ADDRESS1,
                F.COR_TOWN,
                F.COR_POST_CODE,
                F.STATE_COR,
                -- Father
                F.DAD_DOMICILE,
                F.FATHER_TITLE,
                F.DAD_FIRSTNAME,
                F.DAD_SURNAME,
                F.DAD_EMAIL,
                F.DAD_HOME_PHONE,
                F.DAD_MOBILE,
                F.DAD_BUSS_PHONE,
                F.DAD_STATUS,
                -- Mother
                F.MUM_DOMICILE,
                F.MOTHER_TITLE,
                F.MUM_FIRSTNAME,
                F.MUM_SURNAME,
                F.MUM_EMAIL,
                F.MUM_HOME_PHONE,
                F.MUM_MOBILE,
                F.MUM_BUSS_PHONE,
                F.MUM_STATUS,
                -- Guardian
                F.GUARD_DOMICILE,
                F.GUARDIAN_TITLE,
                F.GUARD_FIRSTNAME,
                F.GUARD_SURNAME,
                F.GUARD_EMAIL,
                F.GUARD_HOME_PH,
                F.GUARD_MOBILE,
                F.GUARD_BUSS_PH,
                F.GUARD_STATUS,
                -- Carer
                F.CGIVE_DOMICILE,
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
            'ValidAddressFlag'                 => '',
            'StudentContactAddress1'           => '',
            'StudentContactAddress2'           => '',
            'StudentContactAddress3'           => '',
            'StudentContactSuburb'             => '',
            'StudentContactPostCode'           => '',
            'StudentContactState'              => '',
            'StudentContactPhoneActual'        => '',
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
            $row = $this->getDatabase()->trimRow($row);
            // Father
            // I'm going to assume on the DOMICILE field
            if ($row['DAD_DOMICILE'] === 'Y') {
                $result[] = array_merge(
                    $base,
                    [
                        'StudentContactID'                 => $row['IDENTITY_ID#'],
                        'StudentContactTitle'              => $row['FATHER_TITLE'],
                        'StudentContactPreferred'          => $row['DAD_FIRSTNAME'],
                        'StudentContactLegalFullName'      => $row['DAD_FIRSTNAME'].' '.$row['DAD_SURNAME'],
                        'StudentContactSurname'            => $row['DAD_SURNAME'],
                        'StudentContactDefaultEmail'       => $row['DAD_EMAIL'],
                        'ValidAddressFlag'                 => '',
                        'StudentContactAddress1'           => $row['COR_ADDRESS'],
                        'StudentContactAddress2'           => $row['COR_ADDRESS1'],
                        'StudentContactAddress3'           => '',
                        'StudentContactSuburb'             => $row['COR_TOWN'],
                        'StudentContactPostCode'           => $row['COR_POST_CODE'],
                        'StudentContactState'              => $row['STATE_COR'],
                        'StudentContactPhoneActual'        => $row['DAD_HOME_PHONE'],
                        'StudentContactMobilePhone'        => $row['DAD_MOBILE'],
                        'StudentContactOccupPhone'         => $row['DAD_BUSS_PHONE'],
                        'StudentContactRelation'           => $row['DAD_STATUS'] ?: 'Father',
                    ]
                );
            }
            // Mother
            if ($row['MUM_DOMICILE'] === 'Y') {
                $result[] = array_merge(
                    $base,
                    [
                        'StudentContactID'                 => $row['IDENTITY_ID#'],
                        'StudentContactTitle'              => $row['MOTHER_TITLE'],
                        'StudentContactPreferred'          => $row['MUM_FIRSTNAME'],
                        'StudentContactLegalFullName'      => $row['MUM_FIRSTNAME'].' '.$row['MUM_SURNAME'],
                        'StudentContactSurname'            => $row['MUM_SURNAME'],
                        'StudentContactDefaultEmail'       => $row['MUM_EMAIL'],
                        'ValidAddressFlag'                 => '',
                        'StudentContactAddress1'           => $row['COR_ADDRESS'],
                        'StudentContactAddress2'           => $row['COR_ADDRESS1'],
                        'StudentContactAddress3'           => '',
                        'StudentContactSuburb'             => $row['COR_TOWN'],
                        'StudentContactPostCode'           => $row['COR_POST_CODE'],
                        'StudentContactState'              => $row['STATE_COR'],
                        'StudentContactPhoneActual'        => $row['MUM_HOME_PHONE'],
                        'StudentContactMobilePhone'        => $row['MUM_MOBILE'],
                        'StudentContactOccupPhone'         => $row['MUM_BUSS_PHONE'],
                        'StudentContactRelation'           => $row['MUM_STATUS'] ?: 'Mother',
                    ]
                );
            }
            // Guardian
            if ($row['GUARD_DOMICILE'] === 'Y') {
                $result[] = array_merge(
                    $base,
                    [
                        'StudentContactID'                 => $row['IDENTITY_ID#'],
                        'StudentContactTitle'              => $row['GUARDIAN_TITLE'],
                        'StudentContactPreferred'          => $row['GUARD_FIRSTNAME'],
                        'StudentContactLegalFullName'      => $row['GUARD_FIRSTNAME'].' '.$row['GUARD_SURNAME'],
                        'StudentContactSurname'            => $row['GUARD_SURNAME'],
                        'StudentContactDefaultEmail'       => $row['GUARD_EMAIL'],
                        'ValidAddressFlag'                 => '',
                        'StudentContactAddress1'           => $row['COR_ADDRESS'],
                        'StudentContactAddress2'           => $row['COR_ADDRESS1'],
                        'StudentContactAddress3'           => '',
                        'StudentContactSuburb'             => $row['COR_TOWN'],
                        'StudentContactPostCode'           => $row['COR_POST_CODE'],
                        'StudentContactState'              => $row['STATE_COR'],
                        'StudentContactPhoneActual'        => $row['GUARD_HOME_PH'],
                        'StudentContactMobilePhone'        => $row['GUARD_MOBILE'],
                        'StudentContactOccupPhone'         => $row['GUARD_BUSS_PH'],
                        'StudentContactRelation'           => $row['GUARD_STATUS'] ?: 'Guardian',
                    ]
                );
            }
            // Carer
            if ($row['CGIVE_DOMICILE'] === 'Y') {
                $result[] = array_merge(
                    $base,
                    [
                        'StudentContactID'                 => $row['IDENTITY_ID#'],
                        'StudentContactTitle'              => $row['CGIVE_TITLE'],
                        'StudentContactPreferred'          => $row['CGIVE_FIRSTNAME'],
                        'StudentContactLegalFullName'      => $row['CGIVE_FIRSTNAME'].' '.$row['CGIVE_SURNAME'],
                        'StudentContactSurname'            => $row['CGIVE_SURNAME'],
                        'StudentContactDefaultEmail'       => $row['CGIVE_EMAIL'],
                        'ValidAddressFlag'                 => '',
                        'StudentContactAddress1'           => $row['COR_ADDRESS'],
                        'StudentContactAddress2'           => $row['COR_ADDRESS1'],
                        'StudentContactAddress3'           => '',
                        'StudentContactSuburb'             => $row['COR_TOWN'],
                        'StudentContactPostCode'           => $row['COR_POST_CODE'],
                        'StudentContactState'              => $row['STATE_COR'],
                        'StudentContactPhoneActual'        => $row['CGIVE_HOME_PH'],
                        'StudentContactMobilePhone'        => $row['CGIVE_MOBILE'],
                        'StudentContactOccupPhone'         => $row['CGIVE_BUSS_PH'],
                        'StudentContactRelation'           => $row['CGIVE_STATUS'] ?: 'Caregiver',
                    ]
                );
            }
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
     * Contact.DirectoryIncludeFlag PrimaryDirectoryIncludeFlag,
     * Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag,
     * StudentParentsSeparatedFlag
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
        // TODO: Implement getStudentContactListExtended() method.
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
     * @return array
     */
    public function getStudentUserAccountInfo()
    {
        // TODO: Implement getStudentUserAccountInfo() method.
    }

    /**
     * Returns a list of email parent email addresseses for students.
     *
     * @param array $synergyIds
     *
     * @return array
     */
    public function getParentEmails($synergyIds)
    {
        // TODO: Implement getParentEmails() method.
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
     * @param $externalUserId
     *
     * @return array
     */
    public function getParent($externalUserId)
    {
        // TODO: Implement getParent() method.
    }

    /**
     * Gets the contact info of the person who is a Student's Contact. The ID
     * passed is the $externalUserId of the Contact, not the Student. Anyone who
     * is not supposed to be having their details publicised (via the
     * DirectoryIncludeFlag) are not included in the result set.
     * I think this was used to only update parents of current Schoolbox
     * students, rather than updating everyone blindly
     * Used in Synchronise
     * Accept array of Synergy IDs, or just an int
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
        // TODO: Implement getParentSync() method.
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
     * @return array
     */
    public function getParentUserAccountInfo()
    {
        // TODO: Implement getParentUserAccountInfo() method.
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
     * @param $externalUserId
     *
     * @return array
     */
    public function getStaff($externalUserId)
    {
        // TODO: Implement getStaff() method.
    }

    /**
     * Get staff account information
     * Used in Synchronise
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
        // TODO: Implement getStaffUserAccountInfo() method.
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
     * @return array
     */
    public function getStaffContactListExtended()
    {
        // TODO: Implement getStaffContactListExtended() method.
    }

    /**
     * Gets the current semester for the user.
     *
     * @param string $externalUserId
     *
     * @return string The current semester
     */
    public function getCurrentSemester($externalUserId = null)
    {
        // TODO: Implement getCurrentSemester() method.
    }

    /**
     * Gets the current year for the user.
     * Needs a method because a user could be set on file as being on a
     * previous year.
     *
     * @param string $externalUserId
     *
     * @return string The current file year for the user
     */
    public function getCurrentYear($externalUserId = null)
    {
        // TODO: Implement getCurrentYear() method.
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
     * @param $externalUserId
     * @param $userId
     *
     * @return array
     */
    public function getSubjectList($externalUserId, $userId)
    {
        // TODO: Implement getSubjectList() method.
    }

    /**
     * Get all the enrolled students belonging to $externalUserId
     * (usually a parent)
     * Returns an array of $externalId
     *
     * @param $externalUserId
     *
     * @return array
     */
    public function getEnrolledStudents($externalUserId)
    {
        // TODO: Implement getEnrolledStudents() method.
    }

    /**
     * Get the email address as a string for a particular person
     *
     * @param $externalUserId
     *
     * @return string
     */
    public function getEmail($externalUserId)
    {
        // TODO: Implement getEmail() method.
    }

    /**
     * Get the portrait for a particular user
     * Returns a local file path to the user's portrait
     *
     * @param $externalUserId
     *
     * @return string
     */
    public function getPortrait($externalUserId)
    {
        // TODO: Implement getPortrait() method.
    }

    /**
     * Determine whether someone is a day boarder or not
     *
     * @param $boarderValue
     *
     * @return string
     */
    public function determineBoarderType($boarderValue)
    {
        // TODO: Implement determineBoarderType() method.
    }

    /**
     * Find the housemasterId for a particular house
     *
     * @param $houseID
     *
     * @return string
     */
    public function getHousemasterByHouseId($houseID)
    {
        // TODO: Implement getHousemasterByHouseId() method.
    }

    /**
     * List of campuses a staff member is involved in based on timetable
     *
     * @param int $staffID
     *
     * @return array|bool
     */
    public function getCampusFromTimetable($staffID)
    {
        // TODO: Implement getCampusFromTimetable() method.
    }

    /**
     * Retrieves a list of forms that a student may be in.
     *
     * @return array [['Code' => string, 'Description' => string], ...]
     */
    public function getStudentForms()
    {
        // TODO: Implement getStudentForms() method.
    }

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
}
