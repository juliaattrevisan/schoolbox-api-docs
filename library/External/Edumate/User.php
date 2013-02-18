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
            SELECT carer_number AS ID, full_name, email AS Email, mobile AS MobilePhone, spouse_carer_number AS SpouseID, spouse_full_name,
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
              s.tutor_name AS StudentTutor, s.head_of_house AS StudentHeadOfHouse, s.form_name AS StudentForm,
              s.form_staff_name AS StudentFormStaffName, p.full_name AS StudentEmergencyName,
              p.mobile AS StudentEmergencyPhone
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
            'StudentContactSpouseOccupPhone,StudentContactSpouseDefaultEmail', 'StudentContactType');

        $stmt = $this->getDatabase()->prepare("
            SELECT p.carer_number AS StudentContactID, full_name, email AS StudentContactDefaultEmail,
              mobile AS StudentContactMobilePhone
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

            $parents[] = $this->normaliseFields($row, $caseSensitiveFields);
        }

        return $parents;

    }


    /**
     * Returns the current semester/reporting period (used everywhere)
     * @return bool
     */
    public function getCurrentSemester() {
        $stmt = $this->getDatabase()->prepare("
            SELECT report_period
            FROM db2inst1.schoolbox_report_periods
            WHERE start_date < CURRENT_TIMESTAMP
            AND end_date > CURRENT_TIMESTAMP
        ");

        $stmt->execute();

        $semester = $stmt->fetchColumn();

        if($semester) {
            $_SESSION['FileSemester'] = $semester;
            $_SESSION['FileYear'] = date('Y');
            return true;
        }

        return false;
    }


    /**
     * Returns list of subjects the user is enrolled in
     * @param $externalUserId
     * @param $userId
     * @return array|bool
     */
    public function getSubjectList($externalUserId, $userId) {
        if($this->getGroupHandler()->isStaff($userId)) {
            $sql = "
                SELECT c.class_code AS code, class AS name
                FROM db2inst1.schoolbox_teacherclass tc
                LEFT JOIN db2inst1.schoolbox_classes c ON tc.class_code = c.class_code
                WHERE tc.staff_number = :id
            ";
        }
        elseif($this->getGroupHandler()->isStudent($userId)) {
            $sql = "
                SELECT c.class_code AS code, class AS name
                FROM db2inst1.schoolbox_studentclass sc
                LEFT JOIN db2inst1.schoolbox_classes c ON sc.class_code = c.class_code
                WHERE sc.student_number = :id
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
            $classes[$class['CODE']] = array_change_key_case($class, CASE_LOWER);
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

    public function getPortrait($externalUserId) {
        // TODO find out strategy for locating and returning portraits at TIGS
    }

    /**
     * Sync Method: Student
     */
    public function getStudentUserAccountInfo() {
        // TODO Student Sync
    }

    /**
     * Sync Method: Parent
     */
    public function getParentUserAccountInfo() {
        // TODO Parent Sync
    }

    /**
     * Sync Method: Staff
     */
    public function getStaffUserAccountInfo() {
        // TODO Staff Sync
    }



    /**
     * Method not implemented
     * @deprecated
     */
    public function getParentSync($externalUserId) { }

    /**
     * Duplicate method, use getStudentContactList() instead
     * @deprecated
     */
    public function getParentsOf($externalUserId) {
        return $this->getStudentContactList($externalUserId);
    }

    /**
     * Method not implemented
     * @deprecated
     */
    public function getStudentContactListExtended($yearLevel) { }

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

}
