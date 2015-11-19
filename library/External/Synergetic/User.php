<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 *    This file provides access to synergetic user info      *
 *                                                           *
 ************************************************************/

use Alaress\DebugBar\Instance as DebugBar;

class External_Synergetic_User implements External_UserInterface
{
    /**
     * @var MSSQL
     */
    private $database;
    private $groupHandler;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        if (DebugBar::isEnabled()) {
            $this->getDatabase()->installCollector();
            /** @var \DebugBar\DataCollector\DataCollectorInterface $collector */
            $collector = $this->getDatabase()->getCollector();
            if (!DebugBar::getInstance()->hasCollector($collector->getName())) {
                DebugBar::getInstance()->addCollector($collector);
            }
        }
        $this->setGroup(new Group());
    }

    /**
     * Update a particular custom attribute for a specific user
     *
     * @param $synergyId
     * @param $attributeId
     * @param $newValue
     */
    public function updateCustomAttribute($synergyId, $attributeId, $newValue)
    {
        /* First check if there is an Attribute $attributeId for the current person
         * If there is, update it's fields accordingly; otherwise ignore it
         *
         * The alternative is to create it, but where does AttributeCreatedBy come from?
         * Plus, you need to insert it into the lookup table luCommunityAttributes.
         * That may not be ideal.
         *
         * @pc = Current Parent (this query checks if the current $synergyId is also
         * an current parent)
         */
        $query  = "
            SELECT COUNT(*) AS num
            FROM SynergyOne.dbo.vCommunityAddresses
            LEFT JOIN SynergyOne.dbo.CommunityAttributes
              ON SynergyOne.dbo.CommunityAttributes.ID = vCommunityAddresses.id
            WHERE ConstitCodes LIKE '%@pc%'
            AND CommunityAttributes.AttributeCode = '{$attributeId}'
            AND vCommunityAddresses.id = '{$synergyId}'
        ";
        $result = $this->getDatabase()->getRow($query);
        if ((int)$result['num']) {
            $query = "
                UPDATE SynergyOne.dbo.CommunityAttributes
                SET AttributeValue = \"$newValue\"
                WHERE ID = '{$synergyId}'
                AND AttributeCode = '{$attributeId}'
            ";
            $this->getDatabase()->query($query);
        }
// Deliberately disabled
//		else {
//			$query = "
//				INSERT INTO SynergyOne.dbo.CommunityAttributes (ID, AttributeCode, AttributeValue, AttributeCreatedDate, AttributeModifiedDate)
//				VALUES ('{$synergyId}', '{$attributeId}', '{$newValue}', NOW(), NOW())
//			";
//
//			$this->getDatabase()->query($query);
//		}
    }

    /**
     * Retrieves the user's Synergetic image
     *
     * @param Integer $externalUserId
     *
     * @return Bool|String
     */
    public function getPortrait($externalUserId)
    {
        if (!is_numeric($externalUserId)) {
            return false;
        }
        $query = "
            SELECT vPhotos.ModifiedDate
            FROM SynergyOne.dbo.vPhotos vPhotos
            WHERE vPhotos.ID = '" . $this->getDatabase()->quote($externalUserId) . "'
        ";
        if ($photo = $this->getDatabase()->getRow($query)) {
            $photoPath = Config::get('portrait_dir') . '/' . $externalUserId . '.jpg';
            //TODO: delete photos on rollover
            /* If the cached file doesn't exist, or the synergetic image is more recent than our
                      * last known cached version, regenerate cached version.
                      *
                      * Separate the queries out, fetching the photo data only if it's newer than the cached
                      * version, or if the cache version doesn't already exist.
                      */
            $cacheTime    = @filemtime($photoPath);
            $modifiedTime = strtotime($photo['ModifiedDate']);
            if (!file_exists($photoPath) || ($cacheTime < $modifiedTime)) {
                $query = "
                    SELECT vPhotos.Photo, vPhotos.PhotoType
                    FROM SynergyOne.dbo.vPhotos vPhotos
                    WHERE vPhotos.ID = '" . $this->getDatabase()->quote($externalUserId) . "'
                ";
                $photo = $this->getDatabase()->getRow($query);
                /* Save the image
                          */
                $photoHandle = fopen($photoPath, "w");
                fwrite($photoHandle, $photo['Photo']);
                fclose($photoHandle);
            }
            return $photoPath;
        }
        return false;
    }

    /**
     * Gets the contact info of the person who is a Student's Contact. The ID
     * passed is the Synergetic of the Contact, not the Student.
     *
     * @param $synID
     *
     * @return mixed
     */
    public function getParent($synID)
    {
        $query  = "
            SELECT
                Contact.Title,
                Contact.Preferred,
                Contact.Surname,
                Contact.Email,
                Contact.OccupEmail,
                Contact.DefaultEmailCode,
                Addresses.Phone AS HomePhone,
                Addresses.SilentPhoneFlag,
                Contact.MobilePhone,
                Contact.DirectoryIncludeFlag,
                Contact.SpouseID,
                Spouse.Title SpouseTitle,
                Spouse.Preferred SpousePreferred,
                Spouse.Surname SpouseSurname,
                Spouse.Email SpouseEmail,
                Spouse.OccupEmail SpouseOccupEmail,
                Spouse.DefaultEmailCode SpouseDefaultEmailCode,
                Spouse.MobilePhone SpouseMobilePhone,
                Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag
            FROM
                SynergyOne.dbo.Community Contact
            LEFT JOIN
                SynergyOne.dbo.Addresses Addresses ON Contact.AddressID = Addresses.AddressID
            LEFT JOIN
                SynergyOne.dbo.Community Spouse ON Contact.SpouseID = Spouse.ID
            WHERE
                Contact.ID = '$synID'";
        $parent = $this->getDatabase()->getRow($query);
        if ($parent['DefaultEmailCode'] == 'O') {
            $parent['Email'] = $parent['OccupEmail'];
        }
        if ($parent['SpouseDefaultEmailCode'] == 'O') {
            $parent['SpouseEmail'] = $parent['SpouseOccupEmail'];
        }
        return $parent;
    }

    /**
     * Gets the contact info of the person who is a Student's Contact. The ID
     * passed is the Synergetic of the Contact, not the Student. Anyone who is
     * not supposed to be having their details publicised (via the
     * DirectoryIncludeFlag) are not included in the result set.
     * Accept array of Synergy IDs, or just an int
     *
     * @param $synID
     *
     * @return mixed
     */
    public function getParentSync($synID)
    {
        if (is_array($synID)) {
            $assignation = ' IN (' . implode(',', $synID) . ')';
        } else {
            $assignation = " = '$synID'";
        }
        $query = "
            SELECT
                ID, Title,
                Preferred, Surname,
                Email, OccupEmail, DefaultEmailCode,
                DirectoryIncludeFlag
            FROM SynergyOne.dbo.Community
            WHERE ID $assignation";
        if (is_array($synID)) {
            return $this->getDatabase()->getAll($query);
        }
        return $this->getDatabase()->getRow($query);
    }

    /**
     * Get all the parents emails for a class, or group of students
     *
     * @param array $synergyIds
     *
     * @return array
     */
    public function getParentEmails($synergyIds)
    {
        $emailList = array();
        $id_list   = implode(',', array_map('intval', $synergyIds));
        if (empty($id_list)) {
            return array();
        }
        $query = "
            SELECT
                RelatedID,UseEmail,OccupEmail,Email,DefaultEmail
            FROM
                SynergyOne.dbo.RelationShips r
                JOIN SynergyOne.dbo.vCommunityAddresses ON RelatedID=vCommunityAddresses.ID
            WHERE
                r.ID IN ($id_list)";
        $this->getDatabase()->query($query);
        while ($row = $this->getDatabase()->getNextArray()) {
            switch (strtoupper($row['UseEmail'])) {
                case 'O': //Use occupation email address
                    $emailList[$row['RelatedID']] = $row['OccupEmail'];
                    break;
                case 'P': //Use personal email address
                    $emailList[$row['RelatedID']] = $row['Email'];
                    break;
                case 'D': //Use default email address
                    if(trim($row['DefaultEmail']) != '') {
                        $defaultEmail = explode(';',$row['DefaultEmail']);
                        foreach($defaultEmail as $key=>$email){
                            $emailList[$row['RelatedID'].'-'.$key] = $email;
                        }
                    }
                    break;
                default:
                    break;
            }
        }
        return $emailList;
    }

    /**
     * Get a guy's info from Synergetic
     *
     * @param  $synID
     *
     * @return array|bool
     */
    public function getStudent($synID)
    {
        $currentYear     = $this->getCurrentYear();
        $currentSemester = $this->getCurrentSemester();
        $query   = "SELECT
                    vStudents.StudentID,
                    vStudents.StudentGiven1,
                    vStudents.StudentSurname,
                    vStudents.StudentBirthDate,
                    vStudents.StudentTutorName,
                    vStudents.StudentHeadOfHouse,
                    House.HeadOfHouseID,
                    vStudents.StudentFormStaffName,
                    vStudents.StudentTutorDescription,
                    luForm.ID as TeacherFormID,
                    vStudents.StudentForm,
                    luTutor.ID as TeacherTutorID,
                    vStudents.StudentTutor,
                    luForm.Description as FormDescription,
                    vStudents.StudentHouseDescription,
                    vStudents.StudentHouse,
                    vStudents.StudentYearLevel,
                    vStudents.StudentYearLevelCoordinator,
                    luYearLevel.YearLevelCoordID,
                    vStudents.StudentLockerBook,
                    vStudents.StudentEmergencyName,
                    vStudents.StudentEmergencyPhone,
                    vStudents.StudentEmergencyPhoneAlt,
                    vStudents.StudentEmergency2Name,
                    vStudents.StudentEmergency2Phone,
                    vStudents.StudentEmergency2PhoneAlt,
                    vStudents.StudentEmergency3Name,
                    vStudents.StudentEmergency3Phone,
                    vStudents.StudentEmergency3PhoneAlt,
                    vStudents.StudentMedicalAlert,
                    vStudents.StudentSpecialNeedsFlag,
                    vStudents.StudentStudiesCode,
                    AlertMessage,
                    vStudents.StudentBoarder
                FROM SynergyOne.dbo.vStudents vStudents
                    LEFT JOIN CommunityAlerts
                        ON CommunityAlerts.ID = StudentID
                        AND (CommunityAlerts.AlertDateFrom IS NULL OR CommunityAlerts.AlertDateFrom <= CAST(GETDATE() AS date))
                        AND (CommunityAlerts.AlertDateTo IS NULL OR CommunityAlerts.AlertDateTo >= CAST(GETDATE() AS date))
                    LEFT JOIN luForm ON vStudents.StudentForm = luForm.Code
                    LEFT JOIN luTutor ON vStudents.StudentTutor = luTutor.Code
                    LEFT JOIN luYearLevel ON vStudents.StudentCampus = luYearLevel.Campus
                        AND vStudents.StudentYearLevel = luYearLevel.Code
                    LEFT JOIN luHouse House ON StudentCampus = House.Campus AND StudentHouse = House.Code
                WHERE
                    vStudents.StudentID = '$synID'
                    AND vStudents.FileSemester='" . $this->database->quote($currentSemester) . "'
                    AND vStudents.FileYear='" . $this->database->quote($currentYear) . "'
        ";

		$student = false;
		foreach ($this->getDatabase()->getAll($query) ?: array() as $row) {
			if (empty($student)) {
				// fill in student record
				$student = $row;
            if ($student['StudentBirthDate']
                && preg_match('/([\w]+)\s+([\d]+)\s+([\d]+)/', $student['StudentBirthDate'], $matches)
            ) {
                $student['StudentBirthDate'] = strtotime($matches[2] . ' ' . $matches[1] . ' ' . $matches[3]);
                } else {
                    $student['StudentBirthDate'] = false;
                }
				$student['AlertMessage'] = array($student['AlertMessage']);
			} else {
				// join subsequent alerts
				$student['AlertMessage'][] = $row['AlertMessage'];
			}
		}
		if (!empty($student)) {
			$student['AlertMessage'] = implode(
				"\n",
				array_unique(array_filter($student['AlertMessage']))
			);
		}
            return $student;
        }

    public function getStaff($synID)
    {
        $query = "
            SELECT
                SchoolStaffCode,
                luBuilding.Description AS StaffBuilding,
                StaffRoom,
                StaffExtension,
                StaffMobilePhone,
                StaffCampusDescription,
                StaffCategoryDescription,
                luDepartment.Description AS StaffDepartment,
                StaffVoiceMail
             FROM SynergyOne.dbo.vStaff vStaff
             LEFT JOIN SynergyOne.dbo.luBuilding ON StaffBuilding = luBuilding.Code
             LEFT JOIN SynergyOne.dbo.luDepartment ON StaffDepartment = luDepartment.Code
             WHERE vStaff.StaffID = $synID ";
        $staff = $this->getDatabase()->getRow($query);
        return $staff;
    }

    /**
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
        $query = "
            SELECT
                StudentID,
                StudentGiven1,
                StudentSurname,
                StudentYearLevel,
                House.Description AS StudentHouse,
                StudentBoarder,
                StudentForm,
                StudentContactID,
                StudentContactTitle,
                StudentContactPreferred,
                StudentContactLegalFullName,
                StudentContactSurname,
                StudentContactDefaultEmail,
                vStudentContactAllAddress.ValidAddressFlag,
                StudentContactAddress1,
                StudentContactAddress2,
                StudentContactAddress3,
                StudentContactSuburb,
                StudentContactPostCode,
                StudentContactState,
                StudentCountry.Description AS StudentContactCountryCode,
                StudentContactHomeAddress1,
                StudentContactHomeAddress2,
                StudentContactHomeAddress3,
                StudentContactHomeSuburb,
                StudentContactHomePostCode,
                StudentContactHomeState,
                StudentCountry.Description AS StudentContactHomeCountryCode,
                StudentContactPhoneActual,
                StudentContactMobilePhone,
                StudentContactOccupPhone,
                StudentContactSpouseID,
                StudentContactSpouseTitle,
                StudentContactSpousePreferred,
                StudentContactSpouseLegalFullName,
                StudentContactSpouseSurname,
                StudentContactSpouseMobilePhone,
                StudentContactSpouseOccupPhone,
                StudentContactSpouseDefaultEmail,
                StudentContactType,
                StudentContactSilentPhoneFlag,
                Contact.DirectoryIncludeFlag PrimaryDirectoryIncludeFlag,
                Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag,
                ContactAddress.SilentHomeAddressFlag SilentHomeAddressFlag,
                StudentParentsSeparatedFlag
            FROM SynergyOne.dbo.vStudentContactAllAddress
            LEFT JOIN SynergyOne.dbo.Community Contact ON StudentContactID = Contact.ID
            LEFT JOIN SynergyOne.dbo.Addresses ContactAddress ON Contact.AddressID = ContactAddress.AddressID
            LEFT JOIN SynergyOne.dbo.Community Spouse ON StudentContactSpouseID = Spouse.ID
            LEFT JOIN SynergyOne.dbo.luHouse House ON StudentCampus = House.Campus
                AND StudentHouse = House.Code
            LEFT JOIN SynergyOne.dbo.luYearLevel Year ON StudentYearLevel = Year.Code
                AND StudentCampus = Year.Campus
            LEFT JOIN SynergyOne.dbo.luCountry StudentCountry
                ON StudentContactCountryCode = StudentCountry.Code
            LEFT JOIN SynergyOne.dbo.luCountry StudentHomeCountry
                ON StudentContactHomeCountryCode = StudentHomeCountry.Code
            WHERE (StudentContactType = 'SC1' OR StudentContactType = 'SC2' OR StudentContactType = 'SC3')
            AND FileSemester = '" . $this->database->quote($this->getCurrentSemester()) . "'
            AND FileYear = '" . $this->database->quote($this->getCurrentYear()) . "'"
        ;
        /* Search for a particular year level
         */
        if (strlen($yearlevel)) {
            if (is_numeric($yearlevel)) {
                // forcibly cast to int in case it's a something non-int that's still numeric
                $yearlevel = (int)$yearlevel;
                $query .= "
                AND (StudentYearLevel = '{$yearlevel}' OR Year.Description = '{$yearlevel}')";
            } else {
                // if year is NOT numeric, don't attempt to compare StudentYearLevel (will bug out)
                $query .= "
                AND Year.Description = '{$this->getDatabase()->quote($yearlevel)}'";
            }
        }
        /* Search for a student name
         */
        if (strlen($name)) {
            $name = $this->getDatabase()->quote($name);
            $query .= "
                AND (
                    (StudentSurname LIKE '%{$name}%')
                    OR
                    (StudentGiven1 LIKE '%{$name}%')
                )
            ";
        }
        /* Search for a particular boarding status
         *
         * In Synergetic, assume the following:
         * B: Boarder
         * D: Day student
         * HS: Home stay
         */
        if ($boardingStatus == '') {
            /* No restrictions on result set
             */
        } elseif ($boardingStatus == 'yes') {
            $query .= "
                AND StudentBoarder = 'B'
            ";
        } else {
            $query .= "
                AND StudentBoarder != 'B'
            ";
        }
        /* Search for a particular form
         */
        if ($form) {
            $form = $this->getDatabase()->quote($form);
            $query .= "
                AND StudentForm = '{$form}'
            ";
        }
        $query .= "
            ORDER BY StudentSurname, StudentID, StudentContactType
        ";

        $contactList = $this->getDatabase()->getAll($query) ?: array();
        foreach ($contactList as &$row) {
            if ($row['ValidAddressFlag'] == 0 || $row['SilentHomeAddressFlag'] == 1) {
                $row['StudentContactAddress1']        = '';
                $row['StudentContactAddress2']        = '';
                $row['StudentContactAddress3']        = '';
                $row['StudentContactSuburb']          = '';
                $row['StudentContactPostCode']        = '';
                $row['StudentContactState']           = '';
                $row['StudentContactCountryCode']     = '';
                $row['StudentContactHomeAddress1']    = '';
                $row['StudentContactHomeAddress2']    = '';
                $row['StudentContactHomeAddress3']    = '';
                $row['StudentContactHomeSuburb']      = '';
                $row['StudentContactHomePostCode']    = '';
                $row['StudentContactHomeState']       = '';
                $row['StudentContactHomeCountryCode'] = '';
            }
            $row['StudentContactHomeCountryCode']
                = $row['StudentContactHomeCountryCode'] == 'Not Selected' ? '' : $row['StudentContactHomeCountryCode'];
            $row['StudentContactCountryCode']
                = $row['StudentContactCountryCode'] == 'Not Selected' ? '' : $row['StudentContactCountryCode'];
        }
        return $contactList;
    }

    /**
     * Gets a list of Contacts for all Student Boarders.
     *
     * Used in reporting.
     *
     * @return mixed
     */
    public function getStudentBoarderContactListExtended()
    {
        $query = "
            SELECT
                StudentID,
                StudentGiven1,
                StudentSurname,
                StudentYearLevel,
                House.Description AS StudentHouse,
                StudentContactID,
                StudentContactTitle,
                StudentContactPreferred,
                StudentContactSurname,
                StudentContactDefaultEmail,
                StudentContactAddress1,
                StudentContactAddress2,
                StudentContactAddress3,
                StudentContactSuburb,
                StudentContactPostCode,
                StudentContactState,
                StudentContactCountryDesc,
                StudentContactPhoneActual,
                StudentContactMobilePhone,
                StudentContactOccupPhone,
                StudentContactSpouseID,
                StudentContactSpouseTitle,
                StudentContactSpousePreferred,
                StudentContactSpouseSurname,
                StudentContactSpouseMobilePhone,
                StudentContactSpouseOccupPhone,
                StudentContactSpouseDefaultEmail,
                StudentContactType,
                StudentContactSilentPhoneFlag,
                Contact.DirectoryIncludeFlag PrimaryDirectoryIncludeFlag,
                Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag,
                StudentParentsSeparatedFlag
            FROM SynergyOne.dbo.vStudentContactAllAddress
            LEFT JOIN SynergyOne.dbo.Community Contact ON StudentContactID = Contact.ID
            LEFT JOIN SynergyOne.dbo.Community Spouse ON StudentContactSpouseID = Spouse.ID
            LEFT JOIN SynergyOne.dbo.luHouse House ON StudentCampus = House.Campus AND StudentHouse = House.Code
            WHERE (StudentContactType = 'SC1' OR StudentContactType = 'SC2' OR StudentContactType = 'SC3')
            AND FileSemester = '" . $this->database->quote($this->getCurrentSemester()) . "'
            AND FileYear = '" . $this->database->quote($this->getCurrentYear()) . "'
            AND StudentBoarder = 'B'
            ORDER BY StudentSurname, StudentID, StudentContactType";
        return $this->getDatabase()->getAll($query);
    }

    /**
     * Get a list of student contacts (parents) and their contact information
     *
     * @param $externalUserId
     *
     * @return mixed
     */
    public function getStudentContactList($externalUserId)
    {
        $query       = "
            SELECT
                StudentContactID,
                StudentContactTitle,
                StudentContactPreferred,
                StudentContactSurname,
                StudentContactDefaultEmail,
                ValidAddressFlag,
                StudentContactAddress1,
                StudentContactAddress2,
                StudentContactAddress3,
                StudentContactSuburb,
                StudentContactPostCode,
                StudentContactState,
                StudentContactPhoneActual,
                StudentContactMobilePhone,
                StudentContactOccupPhone,
                StudentContactRelation,
                StudentContactSpouseID,
                StudentContactSpouseTitle,
                StudentContactSpousePreferred,
                StudentContactSpouseSurname,
                StudentContactSpouseMobilePhone,
                StudentContactSpouseOccupPhone,
                StudentContactSpouseDefaultEmail,
                StudentContactType,StudentContactSpouseRelation
            FROM
                SynergyOne.dbo.vStudentContactAllAddress
            WHERE
                StudentID = '$externalUserId'
                AND (StudentContactType = 'SC1' OR StudentContactType = 'SC2' OR StudentContactType = 'SC3')
                AND FileSemester = '" . $this->database->quote($this->getCurrentSemester()) . "'
                AND FileYear = '" . $this->database->quote($this->getCurrentYear()) . "'"
        ;
        $contactList = $this->getDatabase()->getAll($query) ?: array();
        foreach ($contactList as &$row) {
            if ($row['ValidAddressFlag'] == 0) {
                $row['StudentContactAddress1'] = '';
                $row['StudentContactAddress2'] = '';
                $row['StudentContactAddress3'] = '';
                $row['StudentContactSuburb']   = '';
                $row['StudentContactPostCode'] = '';
                $row['StudentContactState']    = '';
            }
        }
        return $contactList;
    }

    protected function findFileYearAndSemester($externalUserId = null)
    {
        if ($externalUserId) {
            $result = $this->getDatabase()->executeProcedure(
                'spsConfigUserDefaultYearSemester',
                array(
                    array('@ID', $externalUserId, SQLINT4, false, false, -1),
                )
            );
            if ($result['success']
                && isset($result['result'][0]['UserFileSemester'])
                && $result['result'][0]['UserFileSemester']
                && isset($result['result'][0]['UserFileYear'])
                && $result['result'][0]['UserFileSemester']
            ) {
                return array(
                    'year'     => $result['result'][0]['UserFileYear'],
                    'semester' => $result['result'][0]['UserFileSemester'],
                );
            }
        }
        // Get current FileSemester and FileYear
        $query = "SELECT FileSemester, FileYear FROM FileSemesters WHERE SystemCurrentFlag = 1";
        if ($semester = $this->getDatabase()->getRow($query)) {
            return array(
                'year'     => $semester['FileYear'],
                'semester' => $semester['FileSemester'],
            );
        } else {
            return array();
        }
    }

    public function getCurrentSemester($externalUserId = null)
    {
        $fileYearSemester = $this->findFileYearAndSemester($externalUserId);
        if (!empty($fileYearSemester)) {
            return $fileYearSemester['semester'];
        }
        return null;
    }

    public function getCurrentYear($externalUserId = null)
    {
        $fileYearSemester = $this->findFileYearAndSemester($externalUserId);
        if (!empty($fileYearSemester)) {
            return $fileYearSemester['year'];
        }
        return null;
    }

    /**
     * Get a list of subjects and their types
     *
     * @param $synergyID
     * @param $userID
     *
     * @return array|bool
     */
    public function getSubjectList($synergyID, $userID)
    {
        if (empty($synergyID)) {
            return false;
        }

        if ($this->getGroup()->isStudent($userID)) {
            $sql = "
                SELECT
                    StudentClasses.ClassCode,
                    SubjectClasses.ClassCampus,
                    SubjectClasses.Description as name,
                    luFileType.Description as classType
                FROM StudentClasses
                INNER JOIN SubjectClasses ON
                    StudentClasses.FileType = SubjectClasses.FileType AND
                    StudentClasses.FileYear = SubjectClasses.FileYear AND
                    StudentClasses.FileSemester = SubjectClasses.FileSemester AND
                    StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND
                    StudentClasses.ClassCode = SubjectClasses.ClassCode AND
                    StudentClasses.FileSemester = '" . $this->database->quote($this->getCurrentSemester($synergyID)) . "' AND
                    StudentClasses.FileYear = '" . $this->database->quote($this->getCurrentYear($synergyID)) . "' AND
                    StudentClasses.ID=$synergyID
                LEFT JOIN luFileType ON StudentClasses.FileType = luFileType.Code
                WHERE
                    StudentClasses.StartDate <= getdate()
                    AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)
                ORDER BY SubjectClasses.FileType ASC, SubjectClasses.Description ASC
            ";
        } else {
            $sql = "
                SELECT
                    DISTINCT(c1.ClassCode),
                    c1.ClassCampus,
                    c2.description as name,
                    luFileType.Description as classType
                FROM SubjectClassStaff c1
                INNER JOIN SubjectClasses c2 ON
                    c1.FileType = c2.FileType AND
                    c1.FileYear = c2.FileYear AND
                    c1.FileSemester = c2.FileSemester AND
                    c1.ClassCampus = c2.ClassCampus AND
                    c1.ClassCode = c2.ClassCode AND
                    c1.FileSemester = '" . $this->database->quote($this->getCurrentSemester($synergyID)) . "' AND
                    c1.FileYear = '" . $this->database->quote($this->getCurrentYear($synergyID)) . "' AND
                    c1.StaffID = $synergyID
                LEFT JOIN luFileType ON c2.FileType = luFileType.Code
            ";
        }
        $this->getDatabase()->query($sql);
        $subjectList = array();
        while ($subject = $this->getDatabase()->getNextArray()) {
            $subjectList[] = array(
                'code'       => $subject['ClassCode'],
                'name'       => $subject['name'],
                'classType'  => $subject['classType'],
                'campusCode' => $subject['ClassCampus']
            );
        }
        if (is_array($subjectList)) {
            return $subjectList;
        } else {
            return false;
        }
    }

    /**
     * Get the students belonging to you
     *
     * @param $synergyID
     *
     * @return array|bool
     */
    public function getEnrolledStudents($synergyID)
    {
        //
        // Valid Relations:
        // SN/DR:       Son/Daughter
        // STSN/STDR:   Stepson/Stepdaugter
        // WD:          Ward
        // GRASN/GRADR: Grandson/Granddaughter
        // FOCD:        Foster child
        $query = "
            SELECT Relationships.RelatedID
            FROM SynergyOne.dbo.Constituencies Constituencies
            LEFT JOIN SynergyOne.dbo.Relationships Relationships ON Relationships.RelatedID = Constituencies.ID
            LEFT JOIN SynergyOne.dbo.Community Community ON Relationships.RelatedID = Community.ID
            WHERE Relationships.ID = {$synergyID}
            AND Constituencies.ConstitCode='@SC'
            AND Relationships.Relationship IN ('SN','STSN','DR','STDR','WD','GRASN','GRADR','FOCD')
            ORDER BY Community.BirthDate
        ";
        return $this->getDatabase()->getAll($query);
    }

    public function getEmail($id)
    {
        $query = "SELECT Email FROM Community WHERE id = $id";
        return $this->getDatabase()->getOne($query);
    }

    /**
     * Gets all Students' account info from Synergetic
     *
     * @return array|bool
     */
    public function getStudentUserAccountInfo()
    {
        $query = "
            SELECT
                vStudents.StudentID,
                NetworkLogin,
                vStudents.StudentTitle,
                vStudents.StudentPreferred,
                vStudents.StudentSurname,
                StudentGender,
                StudentEmail,
                StudentOccupEmail,
                vStudents.StudentYearLevel,
                vStudents.StudentHouse,
				vStudents.StudentBoarder,
				vStudents.StudentBoardingHouse,
                StudentCampus
            FROM SynergyOne.dbo.vStudents vStudents
            LEFT JOIN Community ON Community.ID = StudentID
            WHERE
            FileSemester = '" . $this->database->quote($this->getCurrentSemester()) . "'
            AND FileYear = '" . $this->database->quote($this->getCurrentYear()) . "'
        ";
        return $this->getDatabase()->getAll($query);
    }

    /**
     * {@inheritdoc}
     */
    public function getStudentUserAccountIterator()
    {
        $query = "
            SELECT
                vStudents.StudentID,
                NetworkLogin,
                vStudents.StudentTitle,
                vStudents.StudentPreferred,
                vStudents.StudentSurname,
                StudentGender,
                StudentEmail,
                StudentOccupEmail,
                vStudents.StudentYearLevel,
                vStudents.StudentHouse,
                vStudents.StudentBoarder,
                vStudents.StudentBoardingHouse,
                StudentCampus
            FROM SynergyOne.dbo.vStudents vStudents
            LEFT JOIN Community ON Community.ID = StudentID
            WHERE
            FileSemester = '" . $this->database->quote($this->getCurrentSemester()) . "'
            AND FileYear = '" . $this->database->quote($this->getCurrentYear()) . "'
        ";
        return new External_StudentAccountIterator($this->getDatabase()->getIterator($query));
    }

    /**
     * Gets all Parents' account info from Synergetic
     * Used for Bialik sync
     *
     * @return array|bool
     */
    public function getParentUserAccountInfo()
    {
        $query = "
            SELECT DISTINCT
				cp.NetworkLogin AS StudentContactNetworkLogin,
				s.StudentContactID,
				s.StudentContactTitle,
				s.StudentContactPreferred,
				s.StudentContactSurname,
				s.StudentContactDefaultEmail,
				cs.NetworkLogin AS StudentContactSpouseNetworkLogin,
				s.StudentContactSpouseID,
				s.StudentContactSpouseTitle,
				s.StudentContactSpousePreferred,
				s.StudentContactSpouseSurname,
				s.StudentContactSpouseDefaultEmail,
				s.StudentYearLevel,
				s.StudentCampus,
				s.StudentGender
			FROM SynergyOne.dbo.vStudentContactAllAddress s
			LEFT JOIN SynergyOne.dbo.Community cp ON s.StudentContactID = cp.ID
			LEFT JOIN SynergyOne.dbo.Community cs ON s.StudentContactSpouseID = cs.ID
            WHERE (StudentContactType = 'SC1' OR StudentContactType = 'SC2')
            AND FileSemester = '" . $this->database->quote($this->getCurrentSemester()) . "'
            AND FileYear = '" . $this->database->quote($this->getCurrentYear()) . "'
            ORDER BY StudentContactID
        ";
        return $this->getDatabase()->getAll($query);
    }

    /**
     * Gets all active Staff's account info from Synergetic
     *
     * @return array|bool
     */
    public function getStaffUserAccountInfo()
    {
        $query = "
            SELECT
                vStaff.StaffID,
                NetworkLogin,
                ActiveFlag,
                vStaff.StaffTitle,
                vStaff.StaffPreferredName,
                vStaff.StaffSurname,
                DefaultEmailCode,
                OccupEmail,
                Email,
                vStaff.StaffCampus,
                StaffCategoryType,
                StaffCategory,
                vStaff.StaffDepartment
            FROM SynergyOne.dbo.vStaff vStaff
            LEFT JOIN Community ON Community.ID = StaffID
          WHERE ActiveFlag = 1 AND vStaff.StaffRegistrationType != 'STDNT'
        ";
        return $this->getDatabase()->getAll($query);
    }

    /**
     * {@inheritdoc}
     */
    public function getStaffUserAccountIterator()
    {
        // TODO: Implement getStaffUserAccountIterator() method.
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

        /* Restrict staff categories to provided list */
        $staffCategory = explode(',',\Config::get('synergetic_staff_dir_restrict'));
        $scWhere = '';
        if(is_array($staffCategory) && $staffCategory[0] != '') {
            $db = $this->getDatabase();
            $staffCategoryString = implode(',', array_map(function ($var) use ($db) {
                        return "'".$db->quote($var)."'";
                    }
                    , $staffCategory));
            $scWhere = "AND StaffCategory NOT IN ($staffCategoryString)";
        }


        $query = "
            SELECT
                vStaff.StaffID,
                vStaff.SchoolStaffCode,
                vStaff.StaffTitle,
                vStaff.StaffPreferredName,
                vStaff.StaffSurname,
                vStaff.StaffOccupPhone,
                vStaff.StaffOccupEmail,
                vStaff.StaffExtension,
                vStaff.StaffCampusDescription,
                vStaff.StaffCategory,
                vStaff.StaffCategoryType,
                vStaff.StaffDefaultMobilePhone,
                vStaff.StaffVoiceMail,
                StaffDepartmentDescription,
                JobPositions.JobPositionDescription,
                JobPositions.StaffJobPositionStartDate,
                JobPositions.StaffJobPositionEndDate
            FROM SynergyOne.dbo.vStaff vStaff
            LEFT JOIN SynergyOne.dbo.vStaffJobPositions JobPositions ON vStaff.StaffID = JobPositions.StaffID
            WHERE vStaff.ActiveFlag = 1
            AND StaffCategoryDescription != 'Staff on leave'
            $scWhere
            AND ( JobPositions.StaffJobPositionStartDate < GETDATE()
            OR    JobPositions.StaffJobPositionStartDate IS NULL )
            AND ( JobPositions.StaffJobPositionEndDate > GETDATE()
            OR    JobPositions.StaffJobPositionEndDate IS NULL )
            ORDER BY vStaff.StaffSurname
        ";

        return $this->getDatabase()->getAll($query);
    }


    /**
     * List of campuses a staff member is involved in based on timetable
     *
     * @param int $staffID
     * @return array|bool
     */
    public function getCampusFromTimetable($staffID)
    {
        $staffID = (int)$staffID;
        $query = "SELECT DISTINCT( ClassCampus ) AS campus FROM timetable WHERE StaffID = '$staffID' AND FileYear = ".date('Y');
        return $this->getDatabase()->getAll($query);
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
        if (strlen($boarderValue) === 0) {
            return '';
        }
        $query = "
            SELECT Description
            FROM   luBoarder
            WHERE  Code = '{$this->getDatabase()->quote($boarderValue)}'
        ";
        return $this->getDatabase()->getOne($query);
    }

    public function getHousemasterByHouseId($houseID)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getStudentForms()
    {
        $query = "
            SELECT Code, Description
            FROM SynergyOne.dbo.luForm
            WHERE ActiveFlag = 1
        ";
        return $this->getDatabase()->getAll($query);
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
