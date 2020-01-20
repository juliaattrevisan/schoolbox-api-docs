<?php
/**
 * Implementation of Edumate Class connector
 * For schema information, see http://wiki.schoolbox.com.au/index.php/Interface_Calls
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/

 */
class External_Edumate_Class extends External_Edumate_Connection implements External_ClassInterface {

    /**
     * @inheritdoc
     */
    public function getStudents(string $classCode): array
    {

        // fully qualified table names
        $studentClass = 'SCHOOLBOX_STUDENTCLASS';
        $students = 'SCHOOLBOX_STUDENTS';


        // Setup handling multiple codes
        $codes = explode(',', $classCode);
        if (empty($codes)) {
            return [];
        }

        $db = $this->getConnection();
        array_walk($codes, function(&$n) use($db) {
            $n = "'".$this->quote($n)."'";
        });


        // prepare
        $stmt = $this->getConnection()->prepareQuery("
            SELECT
                FIRSTNAME,
                SURNAME AS LASTNAME,
                $studentClass.STUDENT_NUMBER AS SYNERGY_ID,
                YEAR_LEVEL AS YEARLEVEL,
                HOUSE,
                '' AS StudentMedicalAlert,
                '' AS StudentSpecialNeedsFlag,
                '' AS EnglishSecondFlag,
                '' AS StudentTutorDescription
            FROM  $studentClass
            LEFT JOIN $students ON $studentClass.STUDENT_NUMBER = $students.STUDENT_NUMBER
            WHERE $studentClass.CLASS_CODE IN (".implode(',',$codes).")
            ORDER BY SURNAME ASC
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
     * @inheritdoc
     */
    public function getTeachers(string $classCode, Alaress_Date_Format $date = null): array
    {

        // Setup handling multiple codes
        $codes = explode(',', $classCode);
        if (empty($codes)) {
            return [];
        }
        $db = $this->getConnection();
        array_walk($codes, function(&$n) use($db) {
            $n = "'".$this->quote($n)."'";
        });


        // prepare
        $stmt = $this->getConnection()->prepareQuery("
            SELECT STAFF_NUMBER AS STAFFID
                FROM SCHOOLBOX_TEACHERCLASS
                WHERE CLASS_CODE IN (".implode(',',$codes).")
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

    /**
     * @return array
     */
    public function findClassTypes(): array
    {
        return [];
    }

    /**
     * @param string $filetype
     * @param array  $timetableCode
     *
     * @return array
     */
    public function findClassesByType(string $filetype, array $timetableCode): array
    {
        return [];
    }
}
