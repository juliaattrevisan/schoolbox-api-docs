<?php
/**
 * @author    The Whole Company <support@alaress.com.au>
 * @copyright Copyright 2011-2017 (Hopefully RIP after that), Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\User;

interface External_UserInterface {

    /* Student-specific
     */

    /**
     * Get student info for $externalUserId
     *
     * StudentID,
     * StudentGiven1,
     * StudentSurname,
     * StudentBirthDate, (a unix timestamp)
     * StudentHouseDescription,
     * StudentHouse,
     * HeadOfHouseID,
     * StudentHeadOfHouse,
     * StudentBoarder,
     * Comment,
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
     * @return array
     */
    public function getStudent($externalUserId);

    /**
     * Get a list of student contacts (parents) and their contact information
     * for the student $externalUserId
     *
     * StudentContactID,
     * StudentContactTitle,
     * StudentContactPreferred,
     * StudentContactSurname,
     * StudentContactDefaultEmail,
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
     * @importance high - used on student profile page, and the report card.
     *
     * @param string $externalUserId
     * @return array
     */
    public function getStudentContactList($externalUserId);

    /**
     * Gets a list of Contacts for all Students in a particular year level.
     * The year level is stored in the external db.
     *
     * Used in reporting.
     *
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
     * @importance low - used only on the parent report.
     *
     * @param $yearlevel
     * @param $name
     * @param $boardingStatus
     * @param $form
     * @return array
     */
    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form);

    /**
     * Gets some basic information about a student
     *
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
     * @deprecated No new sync scripts will be developed - use the User Provisioning System instead
     *
     * @return array
     */
    public function getStudentUserAccountInfo();

    /**
     * Returns a list of email parent email addresseses for students.
     *
     * @param array $synergyIds
     *
     * @importance medium - used in the class list to create a list of emails, and the report card.
     *
     * @return array
     */
    public function getParentEmails($synergyIds);

    /* Parent-specific
     */

    /**
     * Gets the contact info of the person who is a Student's Contact.
     * $externalId is that of the Contact, not the Student
     *
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
     * @return array
     */
    public function getParent($externalUserId);

    /**
     * Gets the contact info of the person who is a Student's Contact. The ID
     * passed is the $externalUserId of the Contact, not the Student. Anyone who
     * is not supposed to be having their details publicised (via the
     * DirectoryIncludeFlag) are not included in the result set.
     *
     * I think this was used to only update parents of current Schoolbox
     * students, rather than updating everyone blindly
     *
     * Accept array of Synergy IDs, or just an int
     *
     * @importance low - used in some sync scripts.
     *
     * @param $externalUserId
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
     *
     * or
     *
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
    public function getParentSync($externalUserId);

    /**
     * Get the following fields for all parents
     * (yes they're prefixed as Student-)
     *
     * Used in Synchronise
     *
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
     * @deprecated No new sync scripts will be developed - use the User Provisioning System instead
     *
     * @return array
     */
    public function getParentUserAccountInfo();

    /* Staff-specific
     */

    /**
     * Get the following fields for the staff member $externalUserId
     *
     * SchoolStaffCode,
     * StaffBuilding,
     * StaffRoom,
     * StaffExtension,
     * StaffMobilePhone,
     * StaffDefaultMobilePhone,
     * StaffCampusDescription,
     * StaffCategoryDescription,
     * StaffDepartment,
     * StaffVoiceMail
     *
     * @importance high - used on staff profile page.
     *
     * @param $externalUserId
     * @return array
     */
    public function getStaff($externalUserId);

    /**
     * Get staff account information
     *
     * @importance high - used in sync scripts.
     * @deprecated No new sync scripts will be developed - use the User Provisioning System instead
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
    public function getStaffUserAccountInfo();

    /* Common methods
     */

    /**
     * Get contact list for staff directory
     *
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
    public function getStaffContactListExtended();

    /**
     * Gets the current semester for the user.
     *
     * @importance low - used everywhere, but if you can't get it from the DB,
     *             Jan-Jun: 1, Jul-Dec: 2
     *
     * @param string $externalUserId
     * @return string The current semester
     */
    public function getCurrentSemester($externalUserId = null);

    /**
     * Gets the current year for the user.
     * Needs a method because a user could be set on file as being on a
     * previous year.
     *
     * @importance low - used everywhere, but if you can't get it from the DB,
     *             return the current year.
     *
     * @param string $externalUserId
     * @return string The current file year for the user
     */
    public function getCurrentYear($externalUserId = null);

    /**
     * Get a list of subjects for $externalUserId
     * $externalUserId and $userId are properties of the same person
     *
     * This method internally distinguishes between students and
     * staff by using the Schoolbox $userId
     *
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
     * @return array
     */
    public function getSubjectList(User $user);

    /**
     * Get all the enrolled students belonging to $externalUserId
     * (usually a parent)
     *
     * Returns an array of $externalId
     *
     * @importance high - used to determine a parent's children, and related permissions.
     *
     * @param $externalUserId
     * @return string[][]
     * @var string|int $return[idx]['RelatedID']
     */
    public function getEnrolledStudents($externalUserId);

    /**
     * Get the email address as a string for a particular person
     *
     * @importance low - I'm not sure this is used anywhere yet?
     *
     * @param $externalUserId
     * @return string
     */
    public function getEmail($externalUserId);

    /**
     * Get the portrait for a particular user
     * Returns a local file path to the user's portrait
     *
     * @importance low - most schools manually upload portraits to `config.portrait_dir`.
     *
     * @param $externalUserId
     * @return string
     */
    public function getPortrait($externalUserId);

    /**
     * Determine whether someone is a day boarder or not
     *
     * @importance low - used on profile page, but only to show if student is a boarder
     *
     * @param $boarderValue
     * @return string
     */
    public function determineBoarderType($boarderValue);

    /**
     * Find the housemasterId for a particular house
     *
     * @importance low - I'm not sure this is used anywhere yet?
     *
     * @param $houseID
     * @return string
     */
    public function getHousemasterByHouseId($houseID);

    /**
     * List of campuses a staff member is involved in based on timetable
     *
     * @importance low - used in a few sync scripts.
     *
     * @param int $staffID
     * @return array|bool
     */
    public function getCampusFromTimetable($staffID);

    /**
     * Retrieves a list of forms that a student may be in.
     *
     * @importance low - used on student report only.
     *
     * @return array [['Code' => string, 'Description' => string], ...]
     */
    public function getStudentForms();
}
