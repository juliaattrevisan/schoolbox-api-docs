<?php
/**
 * Implementation of Edumate User connector
 * For schema information, see http://wiki.schoolbox.com.au/index.php/Interface_Calls
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class External_Edumate_User extends External_Edumate_Connection implements External_UserInterface {

    /**
     * Returns information on a parent
     * @param int $externalUserId
     * @return bool|array
     */
    public function getParent($externalUserId) {
        // prepare

        $caseSensitiveFields = array('ID', 'Email', 'MobilePhone', 'SpouseID', 'SpouseMobilePhone');

        $stmt = $this->getDatabase()->prepare("
            SELECT carer_number AS ID, full_name, COMBINED_EMAIL_ADDRESS AS Email, mobile AS MobilePhone, spouse_carer_number AS SpouseID, spouse_full_name,
              spouse_mobile AS SpouseMobilePhone
            FROM db2inst1.schoolbox_parents
            WHERE carer_number = :id
        ");

        // retrieve
        $stmt->execute(array(
            'id' => $externalUserId
        ));

        $parent = $stmt->fetch(PDO::FETCH_ASSOC);

        if($parent === false) {
            return false;
        }

        // manipulate
        // split out [spouse_]full_name fields and try to guess name pieces
        // ** ASSIGNMENT (=) HERE INTENTIONAL, NOT COMPARISON (==)
        if($selfName = $this->guessNamePartsFromFull($parent['FULL_NAME'])) {
            $parent = array_merge($parent, $selfName);
            unset($parent['FULL_NAME']);
        }
        if($spouseName = $this->guessNamePartsFromFull($parent['SPOUSE_FULL_NAME'], 'Spouse')) {
            $parent = array_merge($parent, $spouseName);
            unset($parent['SPOUSE_FULL_NAME']);
        }

        return $this->normaliseFields($parent, $caseSensitiveFields);
    }

    /**
     * Helper function to convert a fullname field into name parts
     * @param $fullName
     * @param string $prefix
     * @return array|bool
     */
    protected function guessNamePartsFromFull($fullName, $prefix = '') {
        // strip out any consecutive or trailing spaces
        $fullName = trim(preg_replace("/\s\s+/", ' ', $fullName));

        if(!strlen($fullName)) {
            return false;
        }

        $parts = explode(' ', $fullName);
        switch(count($parts)) {
            case 2: // one space, take as 'Preferred Surname'
                return array(
                    $prefix.'Preferred' => $parts[0],
                    $prefix.'Surname' => $parts[1],
                );
            case 3: // 2 spaces, take as 'Title Preferred Surname'
                return array(
                    $prefix.'Title' => $parts[0],
                    $prefix.'Preferred' => $parts[1],
                    $prefix.'Surname' => $parts[2],
                );
            default: // anything else, can't work with it, return failure
                return false;
        }
    }


    /**
     * Returns information on a student
     * @param $externalUserId
     */
    public function getStudent($externalUserId) {
        // prepare

        /* FIELDS PRESENT IN SYNERGETIC BUT NOT EDUMATE
                     vStudents.StudentTutorName
                 House.HeadOfHouseID
             vStudents.StudentTutorDescription
                luForm.ID as TeacherFormID
               luTutor.ID as TeacherTutorID
                luForm.Description as FormDescription
             vStudents.StudentHouseDescription
             vStudents.StudentYearLevelCoordinator
           luYearLevel.YearLevelCoordID
             vStudents.StudentLockerBook
             vStudents.StudentEmergencyPhoneAlt
             vStudents.StudentMedicalAlert
             vStudents.StudentSpecialNeedsFlag
                       AlertMessage
             vStudents.StudentBoarder
        */

        // use this for mapping DB2INDICES to NormalIndices
        $caseSensitiveFields = array('StudentID', 'StudentGiven1', 'StudentSurname', 'StudentBirthDate',
            'StudentHouseDescription', 'StudentYearLevel', 'StudentTutor', 'StudentHeadOfHouse', 'StudentForm',
            'StudentFormStaffName', 'StudentEmergencyName', 'StudentEmergencyPhone');

        $stmt = $this->getDatabase()->prepare("
            SELECT s.student_number AS StudentID, s.firstname AS StudentGiven1, s.surname AS StudentSurname,
              s.birthdate AS StudentBirthDate, s.house AS StudentHouseDescription, s.year_level AS StudentYearLevel,
              s.tutor_name AS StudentFormStaffName, s.head_of_house AS StudentHeadOfHouse, s.form_name AS StudentForm,
              s.form_staff_name AS StudentTutor, p.full_name AS StudentEmergencyName,
              p.mobile AS StudentEmergencyPhone,
              '' AS StudentStudiesCode
            FROM db2inst1.schoolbox_students s
            LEFT JOIN db2inst1.schoolbox_parent_student ps ON s.student_number = ps.student_number
            LEFT JOIN db2inst1.schoolbox_parents p ON ps.carer_number = p.carer_number
            WHERE s.student_number = :id
        ");

        // retrieve
        $stmt->execute(array(
            'id' => $externalUserId
        ));

        // manipulate

        // use the first record to plaster the data into an array, or it will be returned false if nonexistent
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$student) {
            return false;
        }

        // turn 'Year 08' into 8
        $student['STUDENTYEARLEVEL'] = preg_replace('/[^0-9]/', '', $student['STUDENTYEARLEVEL']) + 0;

        // sometimes Edumate will return blank emergency contact on the first record...
        if(strlen($student['STUDENTEMERGENCYNAME'])) {
            $start = 2;
        }
        else {
            $start = 1;
            unset($student['STUDENTEMERGENCYNAME']);
            unset($student['STUDENTEMERGENCYPHONE']);
        }

        // parse rows into student - it will be replicated by being joined to parent rows, so need to flatten
        // populate emergency contacts by stacking
        for($i = $start; $i < 4, $row = $stmt->fetch(PDO::FETCH_ASSOC); ++$i) {

            // logic to compensate for Edumate returning blank emergency contact
            if(!strlen($row['STUDENTEMERGENCYNAME'])) {
                --$i;
                continue;
            }

            if($i == 1) {
                $i = '';
            }

            $student['StudentEmergency'.$i.'Name'] = $row['STUDENTEMERGENCYNAME'];
            $student['StudentEmergency'.$i.'Phone'] = $row['STUDENTEMERGENCYPHONE'];

            if($i == '') {
                $i = 1;
            }
        }

        // normalise the birth date: change YYYY-mm-dd to timestamp
        $student['STUDENTBIRTHDATE'] = strtotime($student['STUDENTBIRTHDATE']);

        return $this->normaliseFields($student, $caseSensitiveFields);
    }


    public function getStaff($externalUserId) {
        // TODO: THERE IS NO VIEW FOR THESE DATA YET
    }



    /**
     * Returns a list of contacts for a student
     * @param $externalUserId
     */
    public function getStudentContactList($externalUserId) {

        $caseSensitiveFields = array('StudentContactID', 'StudentContactTitle', 'StudentContactPreferred',
            'StudentContactSurname', 'StudentContactDefaultEmail', 'StudentContactAddress1', 'StudentContactSuburb',
            'StudentContactPostCode', 'StudentContactState', 'StudentContactPhoneActual', 'StudentContactMobilePhone',
            'StudentContactOccupPhone', 'StudentContactSpouseID', 'StudentContactSpouseTitle',
            'StudentContactSpousePreferred', 'StudentContactSpouseSurname', 'StudentContactSpouseMobilePhone',
            'StudentContactSpouseOccupPhone','StudentContactSpouseDefaultEmail', 'StudentContactType');

        $stmt = $this->getDatabase()->prepare("
            SELECT p.carer_number AS StudentContactID, full_name, COMBINED_EMAIL_ADDRESS AS StudentContactDefaultEmail,
              mobile AS StudentContactMobilePhone, p.spouse_carer_number as StudentContactSpouseID, spouse_full_name, spouse_mobile as StudentContactSpouseMobilePhone, COMBINED_EMAIL_ADDRESS AS StudentContactSpouseDefaultEmail
            FROM db2inst1.schoolbox_parents p
            INNER JOIN db2inst1.schoolbox_parent_student ps ON p.carer_number = ps.carer_number
            WHERE ps.student_number = :id
        ");

        $stmt->execute(array(
            'id' => $externalUserId
        ));

        $parents = array();
        while($row = $stmt->fetch(DB2::FETCH_ASSOC)) {
            // split out [spouse_]full_name fields and try to guess name pieces
            // ** ASSIGNMENT (=) HERE INTENTIONAL, NOT COMPARISON (==)
            if($selfName = $this->guessNamePartsFromFull($row['FULL_NAME'], 'StudentContact')) {
                $row = array_merge($row, $selfName);
                unset($row['FULL_NAME']);
            }

            if($spouseName = $this->guessNamePartsFromFull($row['SPOUSE_FULL_NAME'], 'StudentContactSpouse')) {
                $row = array_merge($row, $spouseName);
                unset($row['SPOUSE_FULL_NAME']);
            }
            $parents[] = $this->normaliseFields($row, $caseSensitiveFields);
        }

        return $parents;

    }


    /**
     * Returns the current semester/reporting period (used everywhere)
     * @return bool
     */
    public function getCurrentSemester($externalUserId = null) {
        $stmt = $this->getDatabase()->prepare("
            SELECT report_period
            FROM db2inst1.schoolbox_report_periods
            WHERE start_date < CURRENT_TIMESTAMP
            AND end_date > CURRENT_TIMESTAMP
        ");

        $stmt->execute();

        $semester = $stmt->fetchColumn();

        if ($semester) {
            return $semester;
        }

        return null;
    }

    public function getCurrentYear($externalUserId = null)
    {
        return date('Y');
    }

    /**
     * Returns list of subjects the user is enrolled in
     * @param $externalUserId
     * @param $userId
     * @return array|bool
     */
    public function getSubjectList($externalUserId, $userId) {
        if (empty($externalUserId)) {
            return false;
        }
        if($this->getGroupHandler()->isStaff($userId)) {
            $sql = "
                SELECT c.class_code AS code, class AS name, class_type as classType
                FROM db2inst1.schoolbox_teacherclass tc
                LEFT JOIN db2inst1.schoolbox_classes c ON tc.class_code = c.class_code
                WHERE tc.staff_number = :id
                ORDER BY class_type, name
            ";
        }
        elseif($this->getGroupHandler()->isStudent($userId)) {
            $sql = "
                SELECT c.class_code AS code, class AS name, class_type as classType
                FROM db2inst1.schoolbox_studentclass sc
                LEFT JOIN db2inst1.schoolbox_classes c ON sc.class_code = c.class_code
                WHERE sc.student_number = :id
                ORDER BY class_type, name
            ";
        }
        else {
            return false;
        }

        $stmt = $this->getDatabase()->prepare($sql);

        $stmt->execute(array(
            'id' => $externalUserId
        ));

        $classes = array();

        while($class = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $classes[$class['CODE']] =  array(  'code'=>$class['CODE'],
                                                'name'=>$class['NAME'],
                                                'classType'=>$class['CLASSTYPE']);
        }

        return array_values($classes);
    }


    /**
     * Returns a list of students related to a parent
     * @param $externalUserId
     * @todo note that there is no spouse email field currently available in the edumate views (might be misnamed as `name`)
     */
    public function getEnrolledStudents($externalUserId) {
        // prepare
        $stmt = $this->getDatabase()->prepare("
            SELECT student_number
            FROM db2inst1.schoolbox_parent_student
            WHERE carer_number = :id
        ");

        // retrieve
        $stmt->execute(array(
            'id' => $externalUserId
        ));

        // manipulate
        $children = array();
        while($synId = $stmt->fetchColumn()) {
            // ensure a lack of duplicates
            $children[$synId] = array(
                'RelatedID' => $synId
            );
        }

        return array_values($children);
    }


    public function getEmail($externalUserId) {
        // TODO implement from schoolbox internal rather than external
    }

    /**
     * Returns a local path to the user's portrait
     *
     * @param Integer $externalUserId
     * @return Bool|String
     */
    public function getPortrait ($externalUserId) {

        $portraitPath = realpath( Config::get('portrait_dir') ) . "/$externalUserId.jpg";

        if ( is_readable($portraitPath) ) {
            return $portraitPath;
        }
        else {
            return false;
        }
    }


    /**
     * Sync Method: Student
     */
    public function getStudentUserAccountInfo() {
        $stmt = $this->getDatabase()->prepare('
            SELECT
            STUDENT_NUMBER AS "StudentID",
            USERNAME       AS "NetworkLogin",
            NULL           AS "StudentTitle",
            FIRSTNAME      AS "StudentPreferred",
            SURNAME        AS "StudentSurname",
            EMAIL_ADDRESS  AS "StudentEmail",
            NULL           AS "StudentOccupEmail",
            YEAR_LEVEL     AS "StudentYearLevel",
            HOUSE          AS "StudentHouse",
            NULL           AS "StudentCampus"
            FROM db2inst1.schoolbox_students
        ');
        $stmt->execute();
        return $stmt->fetchAll(DB2::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function getStudentUserAccountIterator()
    {
        // TODO: Implement getStudentUserAccountIterator() method.
    }

    /**
     * Sync Method: Parent
     */
    public function getParentUserAccountInfo() {
	    $stmt = $this->getDatabase()->prepare('
	        SELECT
			p.CARER_NUMBER        AS "StudentContactID",
			NULL                  AS "StudentContactTitle",
			p.FULL_NAME           AS "StudentContactPreferred",
			NULL                  AS "StudentContactSurname",
			p.COMBINED_EMAIL_ADDRESS AS "StudentContactDefaultEmail",
			p.SPOUSE_CARER_NUMBER AS "StudentContactSpouseID",
			NULL                  AS "StudentContactSpouseTitle",
			p.SPOUSE_FULL_NAME    AS "StudentContactSpousePreferred",
			NULL                  AS "StudentContactSpouseSurname",
			p.SPOUSE_EMAIL        AS "StudentContactSpouseDefaultEmail",
			s.YEAR_LEVEL          AS "StudentYearLevel",
			s.STUDENT_NUMBER      AS "StudentID"
            FROM db2inst1.schoolbox_parents AS p
            INNER JOIN db2inst1.schoolbox_parent_student
            	AS ps ON p.CARER_NUMBER = ps.CARER_NUMBER
            INNER JOIN db2inst1.schoolbox_students
            	AS s ON ps.STUDENT_NUMBER = s.STUDENT_NUMBER
            ORDER BY INTEGER(p.CARER_NUMBER) ASC, INTEGER(s.STUDENT_NUMBER) ASC
        ');
	    $stmt->execute();
	    return $stmt->fetchAll(DB2::FETCH_ASSOC);
    }

    /**
     * Sync Method: Staff
     */
    public function getStaffUserAccountInfo() {
        $stmt = $this->getDatabase()->prepare('
            SELECT
                STAFF_NUMBER  AS "StaffID",
                USERNAME      AS "NetworkLogin",
                1             AS "ActiveFlag",
                SALUTATION    AS "StaffTitle",
                FIRSTNAME     AS "StaffPreferredName",
                SURNAME       AS "StaffSurname",
                NULL          AS "DefaultEmailCode",
                EMAIL_ADDRESS AS "OccupEmail",
                EMAIL_ADDRESS AS "Email",
                CAMPUS        AS "StaffCampus",
                NULL          AS "StaffCategoryType",
                NULL          AS "StaffCategory"
             FROM db2inst1.schoolbox_staff
             WHERE USERNAME IS NOT NULL
        ');
        $stmt->execute();
        return $stmt->fetchAll(DB2::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function getStaffUserAccountIterator()
    {
        // TODO: Implement getStaffUserAccountIterator() method.
    }

    /**
     * Method not implemented
     * @deprecated
     */
    public function getParentSync($externalUserId) { }

    /**
     * Returns a list of email parent email addresseses for students.
     *
     * @param array $externalUserIds
     *
     * @return array
     */
    public function getParentEmails($externalUserIds) {
        $emails = array();
        foreach ($externalUserIds as $externalUserId) {
            $userEmails = array();
            foreach ($this->getStudentContactList($externalUserId) as $contact) {
                $userEmails[] = $contact['StudentContactDefaultEmail'];
                $userEmails[] = $contact['StudentContactSpouseDefaultEmail'];
            }
            foreach ($userEmails as $i => $email) {
                $emails[$externalUserId.'-'.$i] = $email;
            }
        }
        return array_filter(array_unique($emails));
    }

    /**
     * Method not implemented
     * @deprecated
     */
    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form) { }

    /**
     * Method not implemented
     * @deprecated
     */
    public function determineBoarderType($boarderValue) { }

    /**
     * Method not implemented
     * @deprecated
     */
    public function getHousemasterByHouseId($houseID) { }

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
}
