<?php

/**
 * External_Denbigh2_User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class External_Denbigh2_User implements External_UserInterface
{

    /**
     * Get student info for $externalUserId
     *
     * StudentID,
     * StudentGiven1,
     * StudentSurname,
     * StudentBirthDate,
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
     * AlertMessage,
     *
     * @param $externalUserId
     * @return array
     */
    public function getStudent($externalUserId)
    {
        $data = array();

        $api = new External_Denbigh2_Api('Students', array(
            'Student ID' => $externalUserId,
        ));

        if (!count($api->getErrors())) {
            $result = $api->getResult()->data[0];

            $data = array(
                'StudentID' => $result->{'Student ID'},
                'StudentGiven1' => $result->{'Given Names'},
                'StudentSurname' => $result->{'Surname'},
                'StudentBirthDate' => $result->{'Date of Birth'},
                'StudentHouseDescription' => $result->{'House'},
                'StudentHouse' => $result->{'House'},
                'HeadOfHouseID' => '',
                'StudentHeadOfHouse' => '',
                'StudentBoarder' => '',
                'StudentForm' => '',
                'FormDescription' => '',
                'StudentFormStaffName' => '',
                'StudentTutor' => '',
                'StudentTutorName' => '',
                'StudentTutorDescription' => '',
                'TeacherFormID' => '',
                'TeacherTutorID' => '',
                'StudentYearLevel' => $result->{'Academic Year'},
                'YearLevelCoordID' => '',
                'StudentYearLevelCoordinator' => '',
                'StudentLockerBook' => '',
                'StudentEmergencyName' => $result->{'Father'} . ' ' . $result->{'Father Surname'},
                'StudentEmergencyPhone' => $result->{'Father Phone'},
                'StudentEmergencyPhoneAlt' => '',
                'StudentEmergency2Name' => $result->{'Mother'} . ' ' . $result->{'Mother Surname'},
                'StudentEmergency2Phone' => $result->{'Mother Phone'},
                'StudentEmergency2PhoneAlt' => '',
                'StudentEmergency3Name' => '',
                'StudentEmergency3Phone' => '',
                'StudentEmergency3PhoneAlt' => '',
                'StudentMedicalAlert' => $result->{'Medical Alert Label'},
                'StudentSpecialNeedsFlag' => $result->{'Special Needs'},
                'AlertMessage' => '',
            );
        }

        return $data;
    }

    /**
     * Get a list of student contacts (parents) and their contact information
     * for the student $externalUserId
     *
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
     * @return array
     */
    public function getStudentContactList($externalUserId)
    {
        $data = array();

        /* Gotta get the famiry code out of the Student result set
         */
        $api = new External_Denbigh2_Api('Students', array(
            'Student ID' => $externalUserId,
        ));

        if (!count($api->getErrors())) {
            $result = $api->getResult()->data[0];

            $api = new External_Denbigh2_Api('Family', array(
                'Family Code' => $result->{'Family Code'},
            ));

            if (!count($api->getErrors())) {
                $family = $api->getResult()->data[0];

                $data = array(
                    'StudentContactID' => '',
                    'StudentContactTitle' => $family->{'Father Title'},
                    'StudentContactPreferred' => $family->{'Father Preferred Name'},
                    'StudentContactSurname' => $family->{'Father Surname'},
                    'StudentContactDefaultEmail' => $family->{'Father email'},
                    'ValidAddressFlag' => '',
                    'StudentContactAddress1' => $family->{'Residential Address Street'},
                    'StudentContactAddress2' => '',
                    'StudentContactAddress3' => '',
                    'StudentContactSuburb' => $family->{'Residential Address Suburb'},
                    'StudentContactPostCode' => $family->{'Residential Address PostCode'},
                    'StudentContactState' => $family->{'Residential Address State'},
                    'StudentContactPhoneActual' => '',
                    'StudentContactMobilePhone' => $family->{'Father Mobile'},
                    'StudentContactOccupPhone' => $family->{'Father Phone'},
                    'StudentContactRelation' => '',
                    'StudentContactSpouseID' => '',
                    'StudentContactSpouseTitle' => $family->{'Mother Title'},
                    'StudentContactSpousePreferred' => $family->{'Mother Preferred Name'},
                    'StudentContactSpouseSurname' => $family->{'Mother Surname'},
                    'StudentContactSpouseMobilePhone' => $family->{'Mother Mobile'},
                    'StudentContactSpouseOccupPhone' => $family->{'Mother Phone'},
                    'StudentContactSpouseDefaultEmail' => $family->{'Mother Email'},
                    'StudentContactType' => '',
                    'StudentContactSpouseRelation' => '',
                );
            }
        }

        return $data;
    }

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
     * @param $yearlevel
     * @param $name
     * @param $boardingStatus
     * @param $form
     *
     * @return array
     */
    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form)
    {
        $params = array();
        $allNameSources = array();
        $pattern = null;
        $collection = array();

        /* Filter by year level
         */
        if (strlen($yearlevel) && is_numeric($yearlevel)) {
            $params['Academic Year'] = (int)$yearlevel;
        }

        /* Filter by name
         */
        if (strlen(trim($name))) {
            $pregName = true;

            /* Thing to look for
             */
            $pattern = "/{$name}/i";

            /* Places to look in
             */
            $allNameSources = array(
                'Given Names', 'First Name', 'Surname', 'Preferred Name'
            );
        }
        else {
            $pregName = false;
        }

        /* TODO: Boarding house and Form information not available?
         */

        /* Proceed
         */
        $api = new External_Denbigh2_Api('Students', $params);

        if (!count($api->getErrors())) {
            $allStudents = $api->getResult()->data;

            /* Filter people out by name, if provided
             * Otherwise just restack them
             */
            foreach ($allStudents as $currentStudent) {

                /* Filter by name
                 */
                if ($pregName) {
                    foreach($allNameSources as $currentSource) {

                        /* Check names
                         */
                        if (preg_match($pattern, $currentStudent->{$currentSource})) {
                            $collection[] = $currentStudent;
                            break;
                        }
                    }
                }
                /* TODO: Check anything else in a similar manner
                 */
                /* Otherwise just stack up everyone
                 */
                else {
                    $collection[] = $currentStudent;
                }
            }
        }

        return $collection;
    }

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
     * @return array
     */
    public function getStudentUserAccountInfo()
    {
        $allStudents = array();

        $api = new External_Denbigh2_Api('Students', array());

        if (!count($api->getErrors())) {
            foreach ($api->getResult()->data as $currentStudent) {
                // TODO: I guess I need the NetworkLogin
                // TODO: Campus isn't being populated
                // TODO: Boarders - dunno

                $student = array(
                    'StudentID' => $currentStudent->{'Student ID'},
                    'NetworkLogin' => '',
                    'StudentTitle' => '',
                    'StudentPreferred' => $currentStudent->{'Preferred Name'},
                    'StudentSurname' => $currentStudent->{'Surname'},
                    'StudentGender' => $currentStudent->{'Gender'},
                    'StudentEmail' => $currentStudent->{'Email Address'},
                    'StudentOccupEmail' => '',
                    'StudentYearLevel' => $currentStudent->{'Academic Year'},
                    'StudentHouse' => $currentStudent->{'House'},
                    'StudentBoarder' => '',
                    'StudentBoardingHouse' => '',
                    'StudentCampus' => $currentStudent->{'Campus'},
                );

                $allStudents[] = $student;
            }
        }

        return $allStudents;
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
        $allEmails = array();

        if (count($synergyIds)) {
            foreach($synergyIds as $currentExternalId) {
                $api = new External_Denbigh2_Api('Students', array(
                    'Student ID' => $currentExternalId,
                ));

                if (!count($api->getErrors())) {
                    $currentStudent = $api->getResult()->data[0];

                    if (strlen(trim($currentStudent->{'email Father'}))) {
                        $allEmails[] = $currentStudent->{'email Father'};
                    }

                    if (strlen(trim($currentStudent->{'email Mother'}))) {
                        $allEmails[] = $currentStudent->{'email Mother'};
                    }
                }
            }
        }

        return $allEmails;
    }

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
     * @param $externalUserId
     * @return array
     */
    public function getParent($externalUserId)
    {
        // TODO: No way to uniquely identify parents?
    }

    /**
     * Gets the contact info of the person who is a Student's Contact. The ID
     * passed is the $externalUserId of the Contact, not the Student. Anyone who
     * is not supposed to be having their details publicised (via the
     * DirectoryIncludeFlag) are not included in the result set.
     *
     * I think this was used to only update parents of current Schoolbox
     * students, rather than updating everyone blindly
     *
     * Used in Synchronise
     *
     * Accept array of Synergy IDs, or just an int
     *
     * @param $externalUserId
     * @return array
     */
    public function getParentSync($externalUserId)
    {
        // TODO: No way to uniquely identify parents?
    }

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
     * @return array
     */
    public function getParentUserAccountInfo()
    {
        $api = new External_Denbigh2_Api('Students', array(
            'Student ID' => $externalUserId,
        ));

        if (!count($api->getErrors())) {
            $currentStudent = $api->getResult()->data[0];
        }
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
     * StaffVoiceMail
     *
     * @param $externalUserId
     * @return array
     */
    public function getStaff($externalUserId)
    {
        // TODO: Implement getStaff() method.
    }

    /**
     * Get staff account information
     *
     * Used in Synchronise
     *
     * @return array
     */
    public function getStaffUserAccountInfo()
    {
        // TODO: Implement getStaffUserAccountInfo() method.
    }

    /**
     * Get contact list for staff directory
     *
     * @return array
     */
    public function getStaffContactListExtended()
    {
        // TODO: Implement getStaffContactListExtended() method.
    }

    /**
     * Find out the current semester and current year
     *
     * This MUST set the two session variables:
     *
     * $_SESSION['FileSemester']
     * $_SESSION['FileYear']
     *
     * @param null $externalUserId
     * @return null
     */
    public function getCurrentSemester($externalUserId = null)
    {
        // TODO: Implement getCurrentSemester() method.
    }

    /**
     * Get a list of subjects for $externalUserId
     * $externalUserId and $userId are properties of the same person
     *
     * This method internally distinguishes between students and
     * staff by using the Schoolbox $userId
     *
     * returns array(
     *      'code'  => $result->{class category code
     *      'name'  => $result->{class name
     *      'classType'  => $result->{a string e.g. academic, music, maths
     *      'campusCode'  => $result->{the campus this class is in
     * )
     *
     * @param $externalUserId
     * @param $userId
     * @return array
     */
    public function getSubjectList($externalUserId, $userId)
    {
        // TODO: Implement getSubjectList() method.
    }

    /**
     * Get all the enrolled students belonging to $externalUserId
     * (usually a parent)
     *
     * @param $externalUserId
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
     * @return string
     */
    public function getHousemasterByHouseId($houseID)
    {
        // TODO: Implement getHousemasterByHouseId() method.
    }
}