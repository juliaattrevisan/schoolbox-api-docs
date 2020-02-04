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
        // Setup handling multiple codes
        $codes = explode(',', $classCode);
        if (empty($codes)) {
            return [];
        }

        array_walk($codes, function (&$n) {
            $n = "'" . $this->quote($n) . "'";
        });

        // prepare
        $stmt = $this->getConnection()->prepareQuery("
            SELECT
                FIRSTNAME,
                SURNAME AS LASTNAME,
                SCHOOLBOX_STUDENTCLASS.STUDENT_NUMBER AS SYNERGY_ID,
                YEAR_LEVEL AS YEARLEVEL,
                HOUSE,
                '' AS StudentMedicalAlert,
                '' AS StudentSpecialNeedsFlag,
                '' AS EnglishSecondFlag,
                '' AS StudentTutorDescription
            FROM  SCHOOLBOX_STUDENTCLASS
            LEFT JOIN SCHOOLBOX_STUDENTS ON SCHOOLBOX_STUDENTCLASS.STUDENT_NUMBER = SCHOOLBOX_STUDENTS.STUDENT_NUMBER
            WHERE SCHOOLBOX_STUDENTCLASS.CLASS_CODE IN (".implode(',',$codes).")
            ORDER BY SURNAME ASC
        ");

        // retrieve
        $stmt->execute();

        // manipulate
        $students = [];
        while ($student = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $students[$student['SYNERGY_ID']] = array_change_key_case($student, CASE_LOWER);
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
