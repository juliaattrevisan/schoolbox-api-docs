<?php
/**
 * Implementation of Edumate Class connector
 * For schema information, see http://wiki.schoolbox.com.au/index.php/Interface_Calls
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class External_Edumate_Class extends External_Edumate_Connection implements External_ClassInterface {

    /**
     * Returns a list of students that are associated with a class
     *
     * @param $classCode
     * @return array
     */
    public function getStudents($classCode) {

        // fully qualified table names
        $studentClass = 'db2inst1.schoolbox_studentclass';
        $students = 'db2inst1.schoolbox_students';


        // Setup handling multiple codes
        $codes = explode(',', $classCode);
        if (empty($codes)) {
            return false;
        }

        $db = $this->getConnection();
        array_walk($codes, function(&$n) use($db) {
            $n = "'".$this->quote($n)."'";
        });


        // prepare
        $stmt = $this->getConnection()->prepareQuery("
            SELECT 
                firstname, 
                surname AS lastname, 
                $studentClass.student_number AS synergy_id, 
                year_level AS yearlevel, 
                house, 
                '' AS StudentMedicalAlert, 
                '' AS StudentSpecialNeedsFlag, 
                '' AS StudentTutorDescription,
                '' AS ClassCampus
            FROM  $studentClass
            LEFT JOIN $students ON $studentClass.student_number = $students.student_number
            WHERE $studentClass.class_code IN (".implode(',',$codes).")
            ORDER BY surname ASC
        ");

        // retrieve
        $stmt->execute();

        // manipulate
        $students = array();
        while($student = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // distinct-ify
            if(is_numeric($student['SYNERGY_ID'])) {
                $student['YEARLEVEL'] = preg_replace("/[^0-9]/", '', $student['YEARLEVEL']);
                $students[$student['SYNERGY_ID']] = array_change_key_case($student, CASE_LOWER);
            }
        }
        return array_values($students);
    }


    /**
     * Returns a list of *THE IDs OF* teachers that are associated with a class (method is misnamed)
     *
     * @param $classCode
     * @return array
     */
    public function getTeacherName($classCode) {

        // Setup handling multiple codes
        $codes = explode(',', $classCode);
        if (empty($codes)) {
            return false;
        }
        $db = $this->getConnection();
        array_walk($codes, function(&$n) use($db) {
            $n = "'".$this->quote($n)."'";
        });


        // prepare
        $stmt = $this->getConnection()->prepareQuery("
            SELECT staff_number AS StaffID
                FROM db2inst1.schoolbox_teacherclass
                WHERE class_code IN (".implode(',',$codes).")
        ");

        // retrieve
        $stmt->execute();

        // manipulate
        $teachers = array();
        while($teacher = $stmt->fetchColumn()) {
            $teachers[] = $teacher;
        }

        return array_unique($teachers);
    }
}
