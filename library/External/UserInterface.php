<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Resource\Storage;
use Schoolbox\User;

/**
 * Provides consistently-structured information about users in the SIS, regardless of underlying SIS implementation
 */
interface External_UserInterface extends External\IConnector
{

    /* Student-specific
     */

    /**
     * Get student info for $externalUserId
     *
     * @param string $externalUserId
     * @return array
     * @internal string return['StudentID']
     * @internal string return['StudentGiven1']
     * @internal string return['StudentSurname']
     * @internal string return['StudentBirthDate']
     * @internal string return['StudentTutorName']
     * @internal string return['StudentHeadOfHouse']
     * @internal string return['HeadOfHouseID']
     * @internal string return['StudentFormStaffName']
     * @internal string return['StudentTutorDescription']
     * @internal string return['TeacherFormID']
     * @internal string return['StudentForm']
     * @internal string return['TeacherTutorID']
     * @internal string return['StudentHouseDescription']
     * @internal string return['StudentHouse']
     * @internal string return['StudentYearLevel']
     * @internal string return['StudentYearLevelCoordinator']
     * @internal string return['YearLevelCoordID']
     * @internal string return['StudentEmergencyName']
     * @internal string return['StudentEmergencyPhone']
     * @internal string return['StudentEmergencyPhoneAlt']
     * @internal string return['StudentEmergency2Name']
     * @internal string return['StudentEmergency2Phone']
     * @internal string return['StudentEmergency2PhoneAlt']
     * @internal string return['StudentEmergency3Name']
     * @internal string return['StudentEmergency3Phone']
     * @internal string return['StudentEmergency3PhoneAlt']
     * @internal string return['StudentMedicalAlert']
     * @internal string return['StudentMobilePhone']
     * @internal string return['StudentSpecialNeedsFlag']        '0' => false, '1' => true, '' => unknown
     * @internal string return['EnglishSecondFlag]               '0' => false, '1' => true, '' => unknown
     * @internal string return['StudentStudiesCode']
     * @internal string return['CourtOrderType']
     * @internal string return['AlertMessage']
     * @internal string return['StudentBoarder']
     * @internal string return['Comment']
     * @internal string return['StudentEnrolmentDate']
     * @internal string return['StudentLeavingDate']
     * @internal string return['StudentIBFlag']
     * @internal string return['IndigenousFlag']
     * @internal string return['StudentTSIFlag']
     * @internal string return['countIndividualEducationPlans']
     *
     * @importance high - used on student profile page
     */
    public function getStudent(string $externalUserId): array;

    /**
     * Get a list of student contacts (parents) and their contact information
     * for the student $externalUserId
     *
     * string return[]['StudentContactID']
     * string return[]['StudentContactTitle']
     * string return[]['StudentContactPreferred']
     * string return[]['StudentContactSurname']
     * string return[]['StudentContactDefaultEmail']
     * string return[]['StudentContactPhoneActual']
     * string return[]['StudentContactSilentPhoneFlag']
     * string return[]['StudentContactMobilePhone']
     * string return[]['StudentContactOccupPhone']
     * string return[]['StudentContactRelation']
     * string return[]['StudentContactSpouseID']
     * string return[]['StudentContactSpouseTitle']
     * string return[]['StudentContactSpousePreferred']
     * string return[]['StudentContactSpouseSurname']
     * string return[]['StudentContactSpouseMobilePhone']
     * string return[]['StudentContactSpouseOccupPhone']
     * string return[]['StudentContactSpouseDefaultEmail']
     * string return[]['StudentContactType']
     * string return[]['StudentContactSpouseRelation']
     *
     * @importance high - used on student profile page, and the report card.
     *
     * @param array $externalUserId
     * @return array
     */
    public function getStudentContactList(array $externalUserId): array;

    /**
     * Gets a list of Contacts for all Students in a particular year level.
     * The year level is stored in the external db.
     *
     * Used in reporting.
     *
     * StudentID,
     * StudentPreferred,
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
     * @param string $yearLevel
     * @param string $name
     * @param string $boardingStatus
     * @param string $form
     * @return array
     */
    public function getStudentContactListExtended(string $yearLevel, string $name, string $boardingStatus, string $form): array;

    /**
     * Returns a list of email parent email addresseses for students.
     *
     * @param array $externalUserIds
     *
     * @importance medium - used in the class list to create a list of emails, and the report card.
     *
     * @return array
     */
    public function getParentEmails(array $externalUserIds): array;


    /**
     * Get a list of all the students and their photo redaction flag
     *
     * Returns a stack of tuples, each of which has the following keys:
     * (id is the user id from schoolbox.user)
     *
     * id
     * firstname
     * lastname
     * yearLevel
     * house
     * external_id
     * photo_redacted
     * redact_photo_modified_at
     *
     * @return array
     */
    public function getPhotoRedactionList(): array;


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
     * WorkPhone,
     * DirectoryIncludeFlag,
     * SpouseID,
     * SpouseTitle,
     * SpousePreferred,
     * SpouseSurname,
     * SpouseEmail,
     * SpouseOccupEmail,
     * SpouseDefaultEmailCode,
     * SpouseMobilePhone,
     * SpouseWorkPhone,
     * SpouseDirectoryIncludeFlag
     *
     * @importance medium - used on parent profile page.
     *
     * @param string $externalUserId
     * @return array
     */
    public function getParent(string $externalUserId): array;



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
     * StaffVoiceMail,
     * StaffHouseDescription
     *
     * @importance high - used on staff profile page.
     *
     * @param string $externalUserId
     * @return array
     */
    public function getStaff(string $externalUserId): array;

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
     *         StaffOccupEmail,
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
    public function getStaffContactListExtended(): array;

    /**
     * Gets the current semester for the user.
     *
     * @importance low - used everywhere, but if you can't get it from the DB,
     *             Jan-Jun: 1, Jul-Dec: 2
     *
     * @param string|null $externalUserId
     * @return string|null The current semester
     */
    public function getCurrentSemester(string $externalUserId = null): ?string;

    /**
     * Gets the current year for the user.
     * Needs a method because a user could be set on file as being on a
     * previous year.
     *
     * @importance low - used everywhere, but if you can't get it from the DB,
     *             return the current year.
     *
     * @param string|null $externalUserId
     * @return string|null The current file year for the user
     */
    public function getCurrentYear(string $externalUserId = null): ?string;

    /**
     * Get a list of subjects for $externalUserId
     * $externalUserId and $userId are properties of the same person
     *
     * This method internally distinguishes between students and
     * staff by using the Schoolbox $userId
     *
     * returns array(
     *     array(
     *         'code'       => string - class category code
     *         'name'       => string - class name
     *         'classType'  => string - e.g. academic, music, maths
     *         'campusCode' => string - the campus this class is in
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
    public function getSubjectList(User $user): array;

    /**
     * Get all the enrolled students belonging to $externalUserId
     * (usually a parent)
     *
     * Returns an array of $externalId
     *
     * @importance high - used to determine a parent's children, and related permissions.
     *
     * @param string   $externalUserId
     * @return string[][]
     * @var string|int $return[idx]['RelatedID']
     */
    public function getEnrolledStudents(string $externalUserId): array;


    /**
     * Get the portrait for a particular user
     * Returns a Storage object for the user's portrait
     *
     * @importance low - this simply calls `$user->getPortrait()` for most SIS.
     *             It may attempt to load the user's portrait from SIS before
     *             saving it into Schoolbox.
     *
     * @param User $user
     * @return Storage\Model|null
     */
    public function getPortrait(User $user): ?Storage\Model;

    /**
     * Determine whether someone is a day boarder or not
     *
     * @importance low - used on profile page, but only to show if student is a boarder
     *
     * @param null|string $boarderValue
     * @return string
     */
    public function determineBoarderType(?string $boarderValue): string;

    /**
     * Returns email addresses for any users who are both:
     * * current housemasters, and
     * * match $externalUserIds
     *
     * @param string[] $externalUserIds
     * @return string[]
     */
    public function getHousemasterEmails(array $externalUserIds): array;

    /**
     * Returns email addresses of tutors for students in $externalUserIds
     *
     * @param string[] $externalUserIds
     * @return string[]
     */
    public function getTutorEmails(array $externalUserIds): array;

    /**
     * List of campuses a staff member is involved in based on timetable
     *
     * @importance low - used in a few sync scripts.
     *
     * @internal string array[]['campus']
     *
     * @param string $staffID
     * @return string[][]
     */
    public function getCampusFromTimetable(string $staffID): array;

    /**
     * Retrieves a list of forms that a student may be in.
     *
     * @importance low - used on student report only.
     *
     * @return array [['Code' => string, 'Description' => string], ...]
     */
    public function getStudentForms(): array;

    /**
     * Retrieves a unique list of all year levels.
     *
     * @importance low - used on student report only.
     *
     * @return array [['Code' => string, 'Description' => string], ...]
     */
    public function getAllYearLevels(): array;
}
