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
        // prepare
        $stmt = $this->getDatabase()->prepare("
            SELECT s.Firstname, s.Surname, s.student_number AS synergy_id, year_level AS yearlevel, s.house
            FROM db2inst1.schoolbox_studentclass sc
            LEFT JOIN db2inst1.schoolbox_students s ON sc.student_number = s.student_number
            WHERE sc.class_code = :code
        ");

        // retrieve
        $stmt->execute(array(
            'code' => $classCode
        ));

        // manipulate
        $students = array();
        while($student = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if(is_numeric($student)) {
                $students[$student['synergy_id']] = $student;
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
        // prepare
        $stmt = $this->getDatabase()->prepare("
            SELECT staff_number
                FROM db2inst1.schoolbox_teacherclass
                WHERE class_code = :code
        ");

        // retrieve
        $stmt->execute(array(
            'code' => $classCode
        ));

        // manipulate
        $teachers = array();
        while($teacher = $stmt->fetchColumn()) {
            if(is_numeric($teacher)) {
                $teachers[$teacher] = $teacher;
            }
        }

        return array_values($teachers);
    }
}
