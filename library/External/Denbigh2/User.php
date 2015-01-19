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

            $dobby = new \Alaress_Date_Format($result->{'Date of Birth'}, 'm/d/y');

            $data = array(
                'StudentID' => $result->{'Student ID'},
                'StudentGiven1' => $result->{'Given Names'},
                'StudentSurname' => $result->{'Surname'},
                'StudentBirthDate' => $dobby->getUnixTimestamp(),
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

                $data[] = array(
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
        $allParents = array();

        $api = new External_Denbigh2_Api('Students', array());

        if (!count($api->getErrors())) {
            $allStudents = $api->getResult()->data;

            foreach($allStudents as $currentStudent) {
                // TODO: Parents have no networkLogin equivalent?
                // TODO: Parents have no unique id?

                $parent = array(
                    'StudentContactNetworkLogin' => '',
                    'StudentContactID' => '',
                    'StudentContactTitle' => $currentStudent->{'Father Title'},
                    'StudentContactPreferred' => $currentStudent->{'Father'},
                    'StudentContactSurname' => $currentStudent->{'Father Surname'},
                    'StudentContactDefaultEmail' => $currentStudent->{'email Father'},
                    'StudentContactSpouseNetworkLogin' => '',
                    'StudentContactSpouseID' => '',
                    'StudentContactSpouseTitle' => $currentStudent->{'Mother Title'},
                    'StudentContactSpousePreferred' => $currentStudent->{'Mother'},
                    'StudentContactSpouseSurname' => $currentStudent->{'Mother Surname'},
                    'StudentContactSpouseDefaultEmail' => $currentStudent->{'email Mother'},
                    'StudentYearLevel' => $currentStudent->{'Academic Year'},
                    'StudentCampus' => $currentStudent->{'Campus'},
                    'StudentGender' => $currentStudent->{'Gender'},
                );

                $allParents[] = $parent;
            }
        }

        return $allParents;
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
        $staff = array();
        
        $api = new External_Denbigh2_Api('Staff', array(
            'Staff Code' => $externalUserId
        ));

        if (!count($api->getErrors())) {
            $currentStaff = $api->getResult()->data[0];

            $staff = array(
                'SchoolStaffCode' => $currentStaff->{'Staff Code'},
                'StaffBuilding' => '',
                'StaffRoom' => '',
                'StaffExtension' => $currentStaff->{'Telephone Extension'},
                'StaffMobilePhone' => '',
                'StaffCampusDescription' => $currentStaff->{'Campus'},
                'StaffCategoryDescription' => '',
                'StaffDepartment' => '',
                'StaffVoiceMail' => '',
            );
        }

        return $staff;
    }

    /**
     * Get staff account information
     *
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
    public function getStaffContactListExtended()
    {
        $staff = array();

        $api = new External_Denbigh2_Api('Staff', array());

        if (!count($api->getErrors())) {
            $currentStaff = $api->getResult()->data[0];

            $staff = array(
                'SchoolStaffCode' => $currentStaff->{'Staff Code'},
                'StaffBuilding' => '',
                'StaffRoom' => '',
                'StaffExtension' => $currentStaff->{'Telephone Extension'},
                'StaffMobilePhone' => '',
                'StaffCampusDescription' => $currentStaff->{'Campus'},
                'StaffCategoryDescription' => '',
                'StaffDepartment' => '',
                'StaffVoiceMail' => '',
            );
        }

        return $staff;
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
     * @return array
     */
    public function getSubjectList($externalUserId, $userId)
    {
        $class = array();

        $api = new External_Denbigh2_Api('Enrolments', array(
            'Student ID' => $externalUserId
        ));

        /* Calling userMapper->findById() for user 689 causes Avondale
         * to time out with Error code: ERR_EMPTY_RESPONSE
         *
         * This is needed to get the campusCode
         */

        if (!count($api->getErrors())) {
            $allEnrolments = $api->getResult()->data;

            foreach($allEnrolments as $currentEnrolment) {

                /* Find out more info about the class
                 */
                $api = new External_Denbigh2_Api('Classes', array(
                    'Class' => $currentEnrolment->{'Class'}
                ));

                if (!count($api->getErrors())) {
                    $classData = $api->getResult()->data[0];

                    // TODO: No campus for class?

                    $class[] = array(
                        'code' => $classData->{'Class'},
                        'name' => $classData->{'Subject Name'},
                        'classType' => 'Academic',
                        'campusCode' => ''
                    );
                }
            }
        }

        return $class;
    }

    /**
     * Get all the enrolled students belonging to $externalUserId
     * (usually a parent)
     *
     * Returns an array of $externalId
     *
     * @param $externalUserId
     * @return array
     */
    public function getEnrolledStudents($externalUserId)
    {
        $ids = array();

        // TODO: Made an assumption that the Family Code can get us there

        $api = new External_Denbigh2_Api('Students', array(
            'Family Code' => $externalUserId
        ));

        if (!count($api->getErrors())) {
            $allStudents = $api->getResult()->data;

            foreach($allStudents as $currentStudent) {
                $ids[] = $currentStudent->{'Student ID'};
            }
        }

        return $ids;
    }

    /**
     * Get the email address as a string for a particular person
     *
     * @param $externalUserId
     * @return string
     */
    public function getEmail($externalUserId)
    {
        $emailAddress = '';

        // TODO: Currently no way of knowing which parent to return email address for

        $api = new External_Denbigh2_Api('Students', array(
            'Student ID' => $externalUserId
        ));

        if (!count($api->getErrors())) {
            $currentStudent = $api->getResult()->data[0];

            /* School address normally
             */
            if (strlen(trim($currentStudent->{'Email Address'}))) {
                $emailAddress = $currentStudent->{'Email Address'};
            }
            /* Try emailing home lol
             */
            else if (strlen(trim($currentStudent->{'email Home'}))) {
                $emailAddress = $currentStudent->{'email Home'};
            }
        }

        return $emailAddress;
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
        if (!is_numeric($externalUserId)) {
            return false;
        }

        $api = new External_Denbigh2_Api('Photos', array(
            'Community ID' => $externalUserId
        ));

        if (!count($api->getErrors())) {
            if (count($api->getResult()->data) && strlen($api->getResult()->data[0]->{'PhotoBase64Encoded'})) {
                $photoPath = Config::get('portrait_dir') . '/' . $externalUserId . '.jpg';

                //TODO: delete photos on rollover

                /* If the cached file doesn't exist, or the synergetic image is more recent than our
                 * last known cached version, regenerate cached version.
                 *
                 * Separate the queries out, fetching the photo data only if it's newer than the cached
                 * version, or if the cache version doesn't already exist.
                 */
                $cacheTime    = @filemtime($photoPath);

                /* Denbigh didn't provide a modified date
                 * $modifiedTime = strtotime($photo['ModifiedDate']);
                 */
                $modifiedTime = $_SERVER['REQUEST_TIME'];

                if (!file_exists($photoPath) || ($cacheTime < $modifiedTime)) {
                    $binary = base64_decode($api->getResult()->data[0]->{'PhotoBase64Encoded'});

                    /* Save the image
                     */
                    $photoHandle = fopen($photoPath, "w");
                    fwrite($photoHandle, $binary);
                    fclose($photoHandle);
                }

                return $photoPath;
            }
        }

        return false;
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