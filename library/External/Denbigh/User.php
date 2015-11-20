<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 *    This file provides access to Kings user info        *
 *                                                           *
 ************************************************************/

class External_Denbigh_User implements External_UserInterface {

    private $internalDatabase;

    private $externalDatabase;

    private $group;

    public function __construct() {
        /* Set internal database */
        $this->setInternalDatabase(Database::getInstance());

        /* Establish connection to external database */
        $database = new Database();

        $hostName = Config::get('external_db_host');
        $databaseName = Config::get('external_db_db');
        $username = Config::get('external_db_user');
        $password = Config::get('external_db_passwd');

        $database->connect("mysql:host=$hostName;dbname=$databaseName", $username, $password, array('throw' => true));

        $this->setExternalDatabase($database);

        $this->setGroup(new Group());
    }

    /**
     * Returns a list of email parent email addresseses for students.  Note this can accept a list of student ids, as per a class list or group
     *
     * @param array $synergyIds
     *
     * @return array
     */
    public function getParentEmails($synergyIds) {

	    $emailList = array();
	    $id_list   = implode(',', array_map('intval', $synergyIds));
        if (empty($id_list)) {
            return array();
        }

	    $statement = $this->getExternalDatabase()->query(
            "
			SELECT Family.email
			FROM Schoolbox_Students
			LEFT JOIN Family ON Schoolbox_Students.familyDenbighID = Family.familyDenbighID
			WHERE Schoolbox_Students.denbighID IN ($id_list)
		"
        );

        while($parent = $statement->fetch(PDO::FETCH_ASSOC)) {
	        $emailList = array_merge(
                $emailList,
                array_map('trim', preg_split('/[;,]/', $parent['email']))
            );
        }

        return $emailList;
    }

    /**
     * Gets a housemaster by a house id
     *
     * @param $houseId
     *
     * @return mixed
     */
    public function getHousemasterByHouseId($houseId) {
        $statement = $this->getExternalDatabase()->query(
            "
			SELECT Houses.housemaster_id
			FROM Houses
			WHERE Houses.id = {$this->getExternalDatabase()->quote($houseId)}
		"
        );

        $requestList = $statement->fetch(PDO::FETCH_ASSOC);

        return $requestList['housemaster_id'];
    }

    public function getParent($externalUserId) {
        // Get addresses with a phone number first.
        // Then, sort by address ID (so that first 2 results have the same
        // address but a different parent entry. First should be this user,
        // second the spouse)
        $statement = $this->getExternalDatabase()->query(
            "
            SELECT
                pe.denbighID, pe.surname, pe.given_names, pe.preferred_name, pe.title, pe.gender, pe.dateOfBirth,
                pa.relationship, a.telephoneHome
            FROM People
            LEFT JOIN Parents ON Parents.peopleDenbighID = People.denbighID
            LEFT JOIN Parents pa ON pa.familyDenbighID = Parents.familyDenbighID
            LEFT JOIN People pe ON pe.denbighID = pa.peopleDenbighID
            LEFT JOIN Addresses a ON a.familyDenbighID = pa.familyDenbighID AND a.addressType = 'Residential Address'
            WHERE
                People.denbighID = {$this->getExternalDatabase()->quote($externalUserId)}
            ORDER BY
                a.telephoneHome IS NOT NULL DESC,
                a.addressDenbighID DESC,
                pe.denbighID = {$this->getExternalDatabase()->quote($externalUserId)} DESC
        "
        );

        $parentList = $statement->fetchAll(PDO::FETCH_ASSOC);

        $adaption = array(
            'Title' => '',
            'Preferred' => '',
            'Surname' => '',
            'Email' => '',
            'MobilePhone' => '',
            'HomePhone' => '',
            'SilentPhoneFlag' => '',
            'DirectoryIncludeFlag' => '',
            'SpouseID' => '',
            'SpouseTitle' => '',
            'SpousePreferred' => '',
            'SpouseSurname' => '',
            'SpouseEmail' => '',
            'SpouseMobilePhone' => '',
            'SpouseDirectoryIncludeFlag' => '',
        );

        if (!empty($parentList[0])) {
            $adaption['Title'] = $parentList[0]['title'];
            $adaption['Preferred'] = $parentList[0]['preferred_name'];
            $adaption['Surname'] = $parentList[0]['surname'];
            $adaption['HomePhone'] = $parentList[0]['telephoneHome'];
            $adaption['SilentPhoneFlag'] = false;
            $adaption['DirectoryIncludeFlag'] = 1;
        }

        // Don't update spouse if user[1] is somehow same user as user[0]
        // (this could happen for sole parent who has multiple addresses)
        if (!empty($parentList[1]) && $parentList[1]['denbighID'] != $externalUserId) {
            $adaption['SpouseID'] = $parentList[1]['denbighID'];
            $adaption['SpouseTitle'] = $parentList[1]['title'];
            $adaption['SpousePreferred'] = $parentList[1]['preferred_name'];
            $adaption['SpouseSurname'] = $parentList[1]['surname'];
            $adaption['SpouseDirectoryIncludeFlag'] = 1;
        }

        return $adaption;
    }

    public function getParentSync($externalUserId) {

    }

    public function getStudent($externalUserId) {
        $statement = $this->getExternalDatabase()->query(
            "
			SELECT pe.denbighID, pe.surname, pe.given_names, pe.preferred_name, pe.title, pe.gender, pe.dateOfBirth,
				stu.tutorGroup, stu.house, stu.academicYear,
				pe_sta.denbighID AS tutor_denbighID, pe_sta.surname AS tutor_surname, pe_sta.preferred_name AS tutor_preferred_name, pe_sta.title AS tutor_title,
				pe_pa.denbighID AS parent_denbighID, pe_pa.surname AS parent_surname, pe_pa.preferred_name AS parent_preferred_name, pe_pa.title AS parent_title,
				fa.EmergencyContact1, fa.EmergencyRelationship1, fa.EmergencyPhone1, fa.EmergencyEmail1,
				stu.boardingStatus
			FROM People pe
			LEFT JOIN Students stu ON stu.peopleDenbighID = pe.denbighID
			/* Student's tutor */
			LEFT JOIN Staff sta ON sta.tutorGroup = stu.tutorGroup
			LEFT JOIN People pe_sta ON pe_sta.denbighID = sta.peopleDenbighID
			/* Student's parents */
			LEFT JOIN Parents pa ON pa.familyDenbighID = stu.familyDenbighID
			LEFT JOIN People pe_pa ON pe_pa.denbighID = pa.peopleDenbighID
			LEFT JOIN Family fa ON stu.familyDenbighID = fa.familyDenbighID
			WHERE pe.denbighID = {$this->getExternalDatabase()->quote($externalUserId)}
			LIMIT 1
		"
        );

        $student = $statement->fetch(PDO::FETCH_ASSOC);

        $adaption = array(
            'StudentID' => '',
            'StudentGiven1' => '',
            'StudentSurname' => '',
            'StudentBirthDate' => '0',
            'StudentTutorName' => '',
            'StudentHeadOfHouse' => '',
            'StudentFormStaffName' => '',
            'StudentTutorDescription' => '',
            'TeacherFormID' => '',
            'StudentForm' => '',
            'TeacherTutorID' => '',
            'StudentTutor' => '',
            'FormDescription' => '',
            'StudentHouseDescription' => '',
            'StudentHouse' => '',
            'StudentYearLevel' => '',
            'StudentLockerBook' => '',
            'StudentEmergencyName' => '',
            'StudentEmergencyPhone' => '',
            'StudentEmergencyPhoneAlt' => '',
            'StudentMedicalAlert' => '',
            'StudentSpecialNeedsFlag' => 0,
            'AlertMessage' => '',
            'StudentBoarder' => '',
            'StudentStudiesCode' => '',
        );

        if (!empty($student)) {
            $adaption['StudentID'] = $student['denbighID'];
            $adaption['StudentGiven1'] = $student['given_names'];
            $adaption['StudentSurname'] = $student['surname'];

            $birthdate = new Alaress_Date_Format($student['dateOfBirth'], 'Y-m-d');
            $adaption['StudentBirthDate'] = $birthdate->getUnixTimestamp();

            $adaption['TeacherTutorID'] = $student['tutor_denbighID'];
            $adaption['StudentTutorName'] = "{$student['tutor_title']} {$student['tutor_preferred_name']} {$student['tutor_surname']}";
            $adaption['StudentTutorDescription'] = $student['tutorGroup'];

            $adaption['StudentHouseDescription'] = $student['house'];
            $adaption['StudentYearLevel'] = $student['academicYear'];

            $adaption['StudentEmergencyName'] = "{$student['EmergencyContact1']} ({$student['EmergencyRelationship1']})";
            $adaption['StudentEmergencyPhone'] = $student['EmergencyPhone1'];
            $adaption['StudentEmergencyPhoneAlt'] = $student['EmergencyEmail1'];

            $adaption['StudentBoarder'] = $student['boardingStatus'];
        }

        return $adaption;
    }

    function getStaff($externalUserId) {
        $statement = $this->getExternalDatabase()->query(
            "
			SELECT p.denbighID, p.surname, p.given_names, p.preferred_name, p.title, p.gender, p.dateOfBirth,
				s.staffCode
			FROM People p
			LEFT JOIN Staff s ON s.peopleDenbighID = p.denbighID
			WHERE p.denbighID = {$this->getExternalDatabase()->quote($externalUserId)}
		"
        );

        $staff = $statement->fetch(PDO::FETCH_ASSOC);

        $adaption = array(
            'SchoolStaffCode' => '',
            'StaffBuilding' => '',
            'StaffRoom' => '',
            'StaffExtension' => '',
            'StaffMobilePhone' => '',
            'StaffCampusDescription' => '',
            'StaffCategoryDescription' => '',
            'StaffDepartment' => ''
        );

        if (!empty($staff)) {
            $adaption['SchoolStaffCode'] = $staff['staffCode'];
        }

        return $adaption;
    }

    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form) {

    }

    /**
     * Get a list of student contacts (parents) and their contact information
     *
     * @param $externalUserId
     * @return mixed
     */
    public function getStudentContactList($externalUserId) {

        $query =             "
			SELECT
				pe_p.denbighID, pe_p.surname, pe_p.given_names, pe_p.preferred_name, pe_p.title, pe_p.email,
				pa_p.familyDenbighID,
				a.street, a.suburb, a.state, a.postcode, a.telephoneHome
			FROM People pe_s
			LEFT JOIN Students s ON s.peopleDenbighID = pe_s.denbighID

			/* Get Parent */
			LEFT JOIN Parents pa_p ON pa_p.familyDenbighID = s.familyDenbighID AND pa_p.type != 'Non Resident'
			LEFT JOIN People pe_p ON pe_p.denbighID = pa_p.peopleDenbighID

			/* Address */
			LEFT JOIN Addresses a ON a.familyDenbighID = s.familyDenbighID
			WHERE pe_s.denbighID = {$this->getExternalDatabase()->quote($externalUserId)}
			GROUP BY pe_p.denbighID";

        $statement = $this->getExternalDatabase()->query($query);

        $contactList = $statement->fetchAll(PDO::FETCH_ASSOC);

        $contactAdaptionList = array();

        if (!empty($contactList)) {
            foreach ($contactList as $contact) {
                $contactAdaption = array(
                    'StudentContactID' => $contact['denbighID'],
                    'StudentContactTitle' => $contact['title'],
                    'StudentContactPreferred' => $contact['preferred_name'],
                    'StudentContactSurname' => $contact['surname'],
                    'StudentContactDefaultEmail' => $contact['email'],
                    'StudentContactAddress1' => $contact['street'],
                    'StudentContactSuburb' => $contact['suburb'],
                    'StudentContactPostCode' => $contact['postcode'],
                    'StudentContactState' => $contact['state'],
                    'StudentContactPhoneActual' => $contact['telephoneHome'],
                    'StudentContactMobilePhone' => '',
                    'StudentContactOccupPhone' => '',
                    'StudentContactSpouseID' => '',
                    'StudentContactSpouseTitle' => '',
                    'StudentContactSpousePreferred' => '',
                    'StudentContactSpouseSurname' => '',
                    'StudentContactSpouseMobilePhon' => '',
                    'StudentContactSpouseOccupPhone' => '',
                    'StudentContactSpouseDefaultEma' => '',
                    'StudentContactType' => 'SC1',
                );

                $contactAdaptionList[] = $contactAdaption;
            }
        }

        return $contactAdaptionList;
    }

    function getCurrentSemester($externalUserId = null) {
        return date('m') < 6 ? 1 : 2;
    }

    public function getCurrentYear($externalUserId = null)
    {
        return date('Y');
    }

    function getSubjectList($externalUserId, $userId) {
        if (empty($externalUserId)) {
            return false;
        }
        if ($this->getGroup()->isStaff($userId)) {
            $statement = $this->getExternalDatabase()->query(
                "
				SELECT cl.classCode as code, co.subjectName as name
				FROM People p
				RIGHT JOIN Staff s ON s.peopleDenbighID = p.denbighID
				RIGHT JOIN Classes cl ON cl.staffCode = s.staffCode
				RIGHT JOIN Courses co ON co.courseCode = cl.courseCode
				WHERE p.denbighID = {$this->getExternalDatabase()->quote($externalUserId)}
				ORDER BY cl.classCode ASC, co.subjectName ASC
			"
            );
        } else {
            $statement = $this->getExternalDatabase()->query(
                "
				SELECT cl.classCode as code, co.subjectName as name
				FROM People p
				RIGHT JOIN Enrolments e ON e.studentID = p.denbighID
				RIGHT JOIN Classes cl ON cl.classCode = e.classCode
				RIGHT JOIN Courses co ON co.courseCode = cl.courseCode
				WHERE p.denbighID = {$this->getExternalDatabase()->quote($externalUserId)}
				ORDER BY cl.classCode ASC, co.subjectName ASC
			"
            );
        }

        $subjectList = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $subjectList;
    }

    function getEnrolledStudents($externalUserId) {
        $statement = $this->getExternalDatabase()->query(
            "
			SELECT s.peopleDenbighID as RelatedID
			FROM People pe
			LEFT JOIN Parents pa ON pa.peopleDenbighID = pe.denbighID
			LEFT JOIN Students s ON s.familyDenbighID = pa.familyDenbighID
			WHERE pe.denbighID = {$this->getExternalDatabase()->quote($externalUserId)}
		"
        );

        $studentList = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $studentList;
    }

    public function getPortrait($externalUserId) {
        //
        // Copied from External_Schoolbox_User strategy
        $portraitPath = realpath( Config::get('portrait_dir') ) . "/$externalUserId.jpg";
        if ( is_readable($portraitPath) ) {
            return $portraitPath;
        }
        else {
            return false;
        }
    }

    public function getEmail($externalUserId) {
        return '';
    }

    /**
     * Gets all Students' account info from Denbigh
     * @return array|bool
     */
    public function getStudentUserAccountInfo() {
        $query = $this->getExternalDatabase()->query(
            "
            SELECT username, academicYear, firstname, surname, denbighID, email, house, cateringHouse, boardingStatus
            FROM Schoolbox_Students"
        );
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function getStudentUserAccountIterator()
    {
        $statement = $this->getExternalDatabase()->prepare("
            SELECT
                denbighID AS StudentID,
                username AS NetworkLogin,
                '' AS StudentTitle,
                firstname AS StudentPreferred,
                surname AS StudentSurname,
                '' AS StudentGender,
                email AS StudentEmail,
                '' AS StudentOccupEmail,
                academicYear AS StudentYearLevel,
                house AS StudentHouse,
                boardingStatus AS StudentBoarder,
                cateringHouse AS StudentBoardingHouse,
                '' AS StudentCampus
            FROM Schoolbox_Student
            ORDER BY denbighID ASC
        ");
        $statement->execute();
        return new \Schoolbox\Sync\Iterator\Student($statement);
    }

    public function getParentUserAccountInfo() {
        $query = $this->getExternalDatabase()->query(
            "
            SELECT peo.title, preferred_name, peo.surname, adr.telephoneHome, peo.denbighID, par.familyDenbighID,
                   peo.email, par.loginID
            FROM Addresses adr
            LEFT JOIN Parents par ON par.familyDenbighID = adr.familyDenbighID
            LEFT JOIN People peo ON peo.denbighID = par.peopleDenbighID
            WHERE peo.id IS NOT NULL
            GROUP BY peo.denbighID
            ORDER BY familyDenbighID ASC, denbighID ASC"
        );
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function getParentUserAccountIterator()
    {
        $statement = $this->getExternalDatabase()->prepare("
            SELECT
                par.loginID AS StudentContactNetworkLogin,
                peo.denbighID AS StudentContactID,
                peo.title AS StudentContactTitle,
                preferred_name AS StudentContactPreferred,
                peo.surname AS StudentContactSurname,
                peo.email AS StudentContactDefaultEmail,
                s.denbighID AS StudentId,
                s.academicYear AS StudentYearLevel,
                '' AS StudentCampus,
                s.gender AS StudentGender,
                s.preferred_name AS StudentPreferred,
                s.surname AS StudentSurname
            FROM Addresses adr
            LEFT JOIN Parents par ON par.familyDenbighID = adr.familyDenbighID
            LEFT JOIN People peo ON peo.denbighID = par.peopleDenbighID
            LEFT JOIN Students s ON s.familyDenbighID = par.familyDenbighID
            WHERE peo.id IS NOT NULL
            GROUP BY peo.denbighID
            ORDER BY familyDenbighID ASC, denbighID ASC
        ");
        $statement->execute();
        return new \Schoolbox\Sync\Iterator\Guardian($statement);
    }

    /**
     * Gets all Staff account info from Denbigh
     * @return array
     */
    public function getStaffUserAccountInfo() {
        $query = $this->getExternalDatabase()->query(
            "
            SELECT People.`denbighID`, surname, preferred_name, title, staffCode, email
            FROM Staff
            LEFT JOIN People ON Staff.`peopleDenbighID` = People.`denbighID`"
        );
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function getStaffUserAccountIterator()
    {
        $statement = $this->getExternalDatabase()->prepare("
            SELECT
                p.denbighID AS StaffID,
                staffCode AS NetworkLogin,
                1 AS ActiveFlag,
                title AS StaffTitle,
                preferred_name AS StaffPreferredName,
                surname AS StaffSurname,
                '' AS DefaultEmailCode,
                '' AS OccupEmail,
                email AS Email,
                '' AS StaffCampus,
                '' AS StaffCategoryType,
                '' AS StaffCategory,
                '' AS StaffDepartment
            FROM Staff s
            LEFT JOIN People p ON s.peopleDenbighID = p.denbighID
        ");
        $statement->execute();
        return new \Schoolbox\Sync\Iterator\Staff($statement);
    }

    /**
     * Determine the boarder type based on a value
     * Returns a human-readable string
     *
     * @param $boarderValue
     * @return string
     */
    public function determineBoarderType($boarderValue) {
        $boarders = array('Boarder'=>'Boarder',
                          'Weekly Boarder' => 'Weekly Boarder',
                          'W' => 'Weekly Boarder',
                          'D' => 'Day Boarder',
                          'B' => 'Full Boarder');

        if(isset($boarders[$boarderValue])) {
            return $boarders[$boarderValue];
        }
        else {
            return false;
        }
    }

    /**
     * Get contact list for staff directory
     *
     * @return array
     */
    public function getStaffContactListExtended() {
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
     * @param Database $internalDatabase
     * @return void
     */
    private function setInternalDatabase(Database $internalDatabase) {
        $this->internalDatabase = $internalDatabase;
    }

    /**
     * @return Database
     */
    private function getInternalDatabase() {
        return $this->internalDatabase;
    }

    /**
     * @param Database $externalDatabase
     * @return void
     */
    private function setExternalDatabase(Database $externalDatabase) {
        $this->externalDatabase = $externalDatabase;
    }

    /**
     * @return Database
     */
    private function getExternalDatabase() {
        return $this->externalDatabase;
    }

    /**
     * @param Group $group
     * @return void
     */
    private function setGroup(Group $group) {
        $this->group = $group;
    }

    /**
     * @return Group
     */
    private function getGroup() {
        return $this->group;
    }
}