<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2008 Lektec Consulting                *
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

        $database->connect("mysql:host=$hostName;dbname=$databaseName", $username, $password);

        $this->setExternalDatabase($database);

        $this->setGroup(new Group());
    }

    /**
     * Get a comma-separated list of parent emails that notifications should be sent to.
     * This is a Kings specific method.
     *
     * @param $externalUserId
     *
     * @return array
     */
    public function getParentEmails($externalUserId) {
        $statement = $this->getExternalDatabase()->query(
            "
			SELECT Family.email
			FROM Schoolbox_Students
			LEFT JOIN Family ON Schoolbox_Students.familyDenbighID = Family.familyDenbighID
			WHERE Schoolbox_Students.denbighID = $externalUserId
		"
        );

        $parentList = $statement->fetch(PDO::FETCH_ASSOC);

        if (is_array($parentList)) {
            $parentEmails = $parentList['email'];
        }

        $allEmails = explode(',', $parentEmails);

        return $allEmails;
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
			WHERE Houses.id = $houseId
		"
        );

        $requestList = $statement->fetch(PDO::FETCH_ASSOC);

        return $requestList['housemaster_id'];
    }

    public function getParent($externalUserId) {
        $statement = $this->getExternalDatabase()->query(
            "
			SELECT pe.denbighID, pe.surname, pe.given_names, pe.preferred_name, pe.title, pe.gender, pe.dateOfBirth,
				pa.relationship, a.telephoneHome
			FROM People
			LEFT JOIN Parents ON Parents.peopleDenbighID = People.denbighID
			LEFT JOIN Parents pa ON pa.familyDenbighID = Parents.familyDenbighID
			LEFT JOIN People pe ON pe.denbighID = pa.peopleDenbighID
			LEFT JOIN Addresses a ON a.familyDenbighID = pa.familyDenbighID
			WHERE People.denbighID = $externalUserId
			ORDER BY pe.denbighID = $externalUserId DESC
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

        if (!empty($parentList[1])) {
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


    /**
     * Get all the parents of the students id
     *
     * @param $synergyIds
     *
     * @return array|bool
     */
    public function getParentsOf($externalUserId) {

    }

    public function getStudent($externalUserId) {
        $statement = $this->getExternalDatabase()->query(
            "
			SELECT pe.denbighID, pe.surname, pe.given_names, pe.preferred_name, pe.title, pe.gender, pe.dateOfBirth,
				stu.tutorGroup, stu.house, stu.academicYear,
				pe_sta.denbighID AS tutor_denbighID, pe_sta.surname AS tutor_surname, pe_sta.preferred_name AS tutor_preferred_name, pe_sta.title AS tutor_title,
				pe_pa.denbighID AS parent_denbighID, pe_pa.surname AS parent_surname, pe_pa.preferred_name AS parent_preferred_name, pe_pa.title AS parent_title,
				fa.EmergencyContact1, fa.EmergencyRelationship1, fa.EmergencyPhone1, fa.EmergencyEmail1,
				a.telephoneHome AS parent_telephoneHome,
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
			/* Parent's address */
			LEFT JOIN Addresses a ON a.familyDenbighID = pa.familyDenbighID
				AND a.telephoneHome IS NOT NULL
			WHERE pe.denbighID = $externalUserId
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
            'StudentBoarder' => ''
        );

        if (!empty($student)) {
            $adaption['StudentID'] = $student['denbighID'];
            $adaption['StudentGiven1'] = $student['given_names'];
            $adaption['StudentSurname'] = $student['surname'];

            $birthdate = new Alaress_Date_Format($student['dateOfBirth'], 'y-m-d');
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
			WHERE p.denbighID = $externalUserId
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
			WHERE pe_s.denbighID = $externalUserId
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

    function getCurrentSemester() {

    }

    function getSubjectList($externalUserId, $userId) {
        if ($this->getGroup()->isStaff($userId)) {
            $statement = $this->getExternalDatabase()->query(
                "
				SELECT cl.classCode as code, co.subjectName as name
				FROM People p
				RIGHT JOIN Staff s ON s.peopleDenbighID = p.denbighID
				RIGHT JOIN Classes cl ON cl.staffCode = s.staffCode
				RIGHT JOIN Courses co ON co.courseCode = cl.courseCode
				WHERE p.denbighID = $externalUserId
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
				WHERE p.denbighID = $externalUserId
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
			WHERE pe.denbighID = $externalUserId
		"
        );

        $studentList = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $studentList;
    }

    public function getPortrait($externalUserId) {
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
     * Determine the boarder type based on a value
     * Returns a human-readable string
     *
     * @param $boarderValue
     * @return string
     */
    public function determineBoarderType($boarderValue) {
        $boarders = array('Boarder', 'Weekly Boarder');

        if (in_array($boarderValue, $boarders)) {
            return 'Boarder';
        }

        return 'Day';
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