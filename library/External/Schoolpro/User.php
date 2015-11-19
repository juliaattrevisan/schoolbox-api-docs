<?php
/**
 * External_Schoolpro_User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class External_Schoolpro_User implements External_UserInterface
{

    /**
     * An instance of MSSQL
     *
     * @var null
     */
    private $db = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDb(MSSQL::getInstance());
    }

    /**
     * Gets the contact info of the person who is a Student's Contact. The ID
     * passed is the Synergetic of the Contact, not the Student.
     *
     * @param $externalId
     * @return array|bool
     */
    public function getParent($externalId)
    {
        $vars = array(
            'sbGetParent',
            array(
                array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pSchoolNum', $externalId, SQLINT4, false, false, -1),
            )
        );

        $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

        if ($result['success'] && count($result['result'])) {
            $currentParent = $result['result'][0];

            $parent = array(
                'ContactID'        => $currentParent['ID'],
                'ContactPreferred' => $currentParent['FirstName'],
                'ContactSurname'   => $currentParent['Surname'],
                'ContactEmail'     => $currentParent['Email'],
                'ContactMobile'    => $currentParent['Mobile'],
                'SpouseID'         => $currentParent['SpouseID'],
                'SpousePreferred'  => $currentParent['SpouseFirstName'],
                'SpouseSurname'    => $currentParent['SpouseSurname'],
                'SpouseEmail'      => $currentParent['SpouseEmail'],
                'SpouseMobile'     => $currentParent['SpouseMobile'],
            );

            return $parent;
        }

        return false;
    }

    /**
     * Gets the contact info of the person who is a Student's Contact. The ID
     * passed is the Synergetic of the Contact, not the Student. Anyone who is
     * not supposed to be having their details publicised (via the
     * DirectoryIncludeFlag) are not included in the result set.
     * Accept array of Synergy IDs, or just an int
     *
     * @param $externalId
     *
     * @return mixed
     */
    public function getParentSync($externalId)
    {
        $vars = array(
            'sbGetParent',
            array(
                array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pSchoolNum', $externalId, SQLINT4, false, false, -1),
            )
        );

        $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

        new \Alaress_Probe($result);

        if ($result['success']) {
        } else {
        }
    }

    /**
     * TODO: Not specified in a stored proc
     *
     * Get all the parents emails for a class, or group of students
     *
     * @param array $synergyIds
     *
     * @return array
     */
    public function getParentEmails($synergyIds)
    {
        return array();
    }

    /**
     * Get a guy's info from Schoolpro
     *
     * @param  $synId
     * @return array|bool
     */
    public function getStudent($synId)
    {
        $medicalNoteStr = '';

        $vars = array(
            'sbGetStudent',
            array(
                array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pSchoolNum', $synId, SQLINT4, false, false, -1),
            )
        );

        $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

        if ($result['success'] && count($result['result'])) {
            $currentStudent = $result['result'][0];

            /* Get the medical data for him too
             */
            $medicalVars = array(
                'sbGetStudentMedicalConditions',
                array(
                    array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                    array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
                    array('@pSchoolNum', $synId, SQLINT4, false, false, -1),
                )
            );

            $medicalResult = $this->getDb()->executeProcedure($medicalVars[0], $medicalVars[1]);

            /* These arrive in a variety of formats
             */
            if ($medicalResult['success'] && count($medicalResult['result'])) {
                foreach ($medicalResult['result'] as $currentCondition) {

                    /* Only include medical issues that are Applicable == 1
                     * Just use a basic message to hide the sensitive data,
                     * and bust out of the loop after the first positive result
                     */
                    if ($currentCondition['Applicable']) {
                        $medicalNoteStr = 'This student has medical conditions.';
                        break;
                    }
                }
            }

            $student = array(
                'StudentID'               => $currentStudent['ID'],
                'StudentGiven1'           => $currentStudent['Firstname'],
                'StudentSurname'          => $currentStudent['Surname'],
                'StudentBirthDate'        => $currentStudent['Birthdate'],
                'StudentHouse'            => $currentStudent['HeadOfHouse'],
                'StudentYearLevel'        => $currentStudent['YearLevel'],
                'StudentTutor'            => $currentStudent['TutorName'],
                'StudentForm'             => $currentStudent['FormName'],
                'StudentFormStaffName'    => $currentStudent['FormStaffName'],
                'StudentEmergencyName'    => $currentStudent['EmergencyContactName'],
                'StudentEmergencyPhone'   => $currentStudent['EmergencyContactPhone'],
                'StudentStudiesCode'      => '',
                'StudentMedicalAlert'     => $medicalNoteStr,
                'StudentSpecialNeedsFlag' => '',
                'AlertMessage'            => '',
            );

            return $student;
        }

        return false;
    }

    /**
     * TODO: Not specified in a stored proc
     *
     * Get a guy's info from Schoolpro
     *
     * @param  $synId
     * @return array|bool
     */
    public function getStaff($synId)
    {
    }

    /**
     * Get a list of student contacts (parents) and their contact information
     *
     * @param $externalUserId
     * @return mixed
     */
    public function getStudentContactList($externalUserId)
    {
        $vars = array(
            'sbGetStudentContact',
            array(
                array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pSchoolNum', $externalUserId, SQLINT4, false, false, -1),
            )
        );

        $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

        if ($result['success'] && count($result['result'])) {
            $currentStudentContact = $result['result'][0];

            $studentContact = array(

                /* This should be separated into constituent parts
                 */
                'StudentContactPreferred'          => $currentStudentContact['FullName'],
                'StudentContactSpousePreferred'    => $currentStudentContact['SpouseName'],
                'StudentContactAddress1'           => $currentStudentContact['LocationLine'],
                'StudentContactID'                 => $currentStudentContact['ID'],
                'StudentContactDefaultEmail'       => $currentStudentContact['email'],
                'StudentContactSuburb'             => $currentStudentContact['Suburb'],
                'StudentContactPostCode'           => $currentStudentContact['Postcode'],
                'StudentContactState'              => $currentStudentContact['State'],
                'StudentContactPhoneActual'        => $currentStudentContact['HomePhone'],
                'StudentContactMobilePhone'        => $currentStudentContact['Mobile'],
                'StudentContactOccupPhone'         => $currentStudentContact['BusinessPhone'],
                'StudentContactRelation'           => '',
                'StudentContactSpouseID'           => $currentStudentContact['SpouseID'],
                'StudentContactSpouseMobilePhone'  => $currentStudentContact['SpouseMobile'],
                'StudentContactSpouseOccupPhone'   => $currentStudentContact['SpouseBusinessPhone'],
                'StudentContactSpouseDefaultEmail' => '',
                'StudentContactType'               => '',
                'StudentContactSpouseRelation'     => '',
            );

            return array($studentContact);
        }

        return false;
    }
    
    /**
     * TODO: Not specified in a stored proc
     *
     * Gets a list of Contacts for all Students in a particular year level. The
     * year level is stored in Synergetic.
     *
     * Used in reporting.
     *
     * Note that there is no StudentContactHomeCountryDesc in the Synergetic views
     * so I changed it to use CountryCode instead.
     *
     * @param $yearlevel
     * @param $name
     * @param $boardingStatus
     * @param $form
     *
     * @return mixed
     */
    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form)
    {
    }
    
    public function getCurrentYear($externalUserId = null)
    {
        return date('Y');
    }

    /**
     * TODO: Not specified in a stored proc
     *
     * @param null $externalUserId
     */
    public function getCurrentSemester($externalUserId = null) {
        return date('m') < 6 ? 1 : 2;
    }
    
    /**
     * Get a list of subjects for a particular user
     *
     * @param $externalUserId
     * @param $userId
     * @return array|bool
     */
    public function getSubjectList($externalUserId, $userId)
    {
        $groupHandler = new Group();

        if ($groupHandler->isStudent($userId)) {
            $vars = array(
                'sbGetStudentSubjectList',
                array(
                    array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                    array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
                    array('@pSchoolNum', $externalUserId, SQLINT4, false, false, -1),
                )
            );

            $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

            if ($result['success'] && count($result['result'])) {
                $subjectList = array();

                foreach ($result['result'] as $currentSubject) {
                    $subject = array(
                        'code' => $currentSubject['ClassID'],
                        'name' => $currentSubject['ItemName']
                    );

                    $subjectList[] = $subject;
                }

                return $subjectList;
            }
        } /* Otherwise assume staff
		 */
        else {
            $vars = array(
                'sbGetStaffSubjectList',
                array(
                    array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                    array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
                    array('@pSchoolNum', $externalUserId, SQLINT4, false, false, -1),
                )
            );

            $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

            if ($result['success'] && count($result['result'])) {
                $subjectList = array();

                /* TODO: Make stored proc consistent with the StudentSubjectList one
                 * Have to confer with Scoolpro to do that.
                 */
                foreach ($result['result'] as $currentSubject) {
                    $subject = array(
                        'code' => $currentSubject['ID'],
                        'name' => $currentSubject['ItemName']
                    );

                    $subjectList[] = $subject;
                }

                return $subjectList;
            }
        }

        return false;
    }

    /**
     * Returns a list of externalIds
     *
     * @param $synergyID
     * @return array|bool
     */
    public function getEnrolledStudents($synergyID)
    {
        $vars = array(
            'sbGetEnrolledStudents',
            array(
                array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pSchoolNum', $synergyID, SQLINT4, false, false, -1),
            )
        );

        $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

        if ($result['success'] && count($result['result'])) {
            $allStudents = array();

            foreach ($result['result'] as $currentStudent) {
                $allStudents[] = ['RelatedID' => $currentStudent['RelationSchoolNum']];
            }

            return $allStudents;
        }

        return false;
    }

    /**
     * Get an email address for a particular person
     *
     * @param $externalUserId
     * @return bool
     */
    public function getEmail($externalUserId)
    {
        $groupHandler = new Group();

        /* 13.5 context
        $userMapper = new Schoolbox\UserMapper();
        $user = $userMapper->findOneByColumnList(array('externalId' => $externalUserId));
        */

        /* 13.0.x context
         */
        $userMapper = new User();
        $user       = $userMapper->getBySynergy($externalUserId);

        /* Use a different query depending on the type of person
         */
        if ($groupHandler->isStudent($user['uid'])) {
            $result = $this->getStudent($externalUserId);

            return $result['email'];
        } else {
            if ($groupHandler->isParent($user['uid'])) {
                $result = $this->getParent($externalUserId);

                return $result['email'];
            } else {
                if ($groupHandler->isStaff($user['uid'])) {

                    /* TODO: Not supplied?
                     */
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Returns a local path to the user's portrait
     *
     * This indicates that Cedar needs to upload the pics manually to portrait_dir
     *
     * @param Integer $externalUserId
     * @return Bool|String
     */
    public function getPortrait($externalUserId)
    {
        if (!is_numeric($externalUserId)) {
            return false;
        }

        $vars = array(
            'sbGetPortraitLastUpdated',
            array(
                array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pSchoolNum', $externalUserId, SQLINT4, false, false, -1),
            )
        );

        $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

        /* Find the last updated datetime
         */
        if ($result['success'] && count($result['result'])) {
            $photoPath = Config::get('portrait_dir') . '/' . $externalUserId . '.jpg';

            //TODO: delete photos on rollover

            /* If the cached file doesn't exist, or the synergetic image is more recent than our
             * last known cached version, regenerate cached version.
             *
             * Separate the queries out, fetching the photo data only if it's newer than the cached
             * version, or if the cache version doesn't already exist.
             */
            $cacheTime    = @filemtime($photoPath);
            $modifiedTime = strtotime($result['result'][0]['CaptureDate']);

            if (!file_exists($photoPath) || ($cacheTime < $modifiedTime)) {
                $vars = array(
                    'sbGetPortraitFile',
                    array(
                        array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                        array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
                        array('@pSchoolNum', $externalUserId, SQLINT4, false, false, -1),
                    )
                );

                $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

                /* Get the binary data and dump it into a file
                 */
                if ($result['success'] && count($result['result'])) {

                    /* Purge all previously transformed copies
                     */
                    $pattern = Config::get('stream_dir') . "/portrait/" . $externalUserId . '-*.dat';

                    foreach (glob($pattern) as $oldCacheFile) {
                        unlink($oldCacheFile);
                    }

                    /* Save the image
                     */
                    $photoHandle = fopen($photoPath, "w");
                    fwrite($photoHandle, $result['result'][0]['RawData']);
                    fclose($photoHandle);
                }
            }

            return $photoPath;
        }

        return false;
    }

    /**
     * Stub method, probably not used
     */
    public function getStudentUserAccountInfo()
    {
        $vars = array(
            'sbGetStudentAccounts',
            array(
                array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
            )
        );

        $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

        new \Alaress_Probe($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getStudentUserAccountIterator()
    {
        // TODO: Implement getStudentUserAccountIterator() method.
    }

    /**
     * Stub method, probably not used
     */
    public function getParentUserAccountInfo()
    {
        $vars = array(
            'sbGetGuardianAccounts',
            array(
                array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
            )
        );

        $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

        new \Alaress_Probe($result);
    }

    /**
     * Stub method, probably not used
     */
    public function getStaffUserAccountInfo()
    {
        $vars = array(
            'sbGetStaffAccounts',
            array(
                array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
            )
        );

        $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

        new \Alaress_Probe($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getStaffUserAccountIterator()
    {
        // TODO: Implement getStaffUserAccountIterator() method.
    }

    /**
     * Stub method, probably not used
     */
    public function determineBoarderType($boarderValue)
    {
    }

    /**
     * Stub method, probably not used
     */
    public function getHousemasterByHouseId($houseID)
    {
    }

    /**
     * Get contact list for staff directory
     *
     * @return array
     */
    public function getStaffContactListExtended()
    {
        // TODO: Implement getStaffContactListExtended() method.
        return array();
    }

    /**
     * List of campuses a staff member is involved in based on timetable
     *
     * @param int $staffID
     * @return array|bool
     */
    public function getCampusFromTimetable($staffID)
    {
        return array();
    }

    /**
     * Not yet implemented for this connector
     *
     * @return array
     */
    public function getStudentForms()
    {
        return [];
    }

    /* Getters/Setters
     */

    /**
     * Set db
     *
     * @param null $db
     */
    private function setDb($db)
    {
        $this->db = $db;
    }

    /**
     * Get db
     *
     * @return null
     */
    private function getDb()
    {
        return $this->db;
    }
}