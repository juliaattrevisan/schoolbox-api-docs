<?php

/**
 * Implementation of Edumate Class connector
 * For schema information, see http://wiki.schoolbox.com.au/index.php/Interface_Calls
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/

 */
class External_Edumate_Class extends External_Edumate_Connection implements External_ClassInterface
{
    /**
     * @inheritdoc
     */
    public function getStudents(string $classCode): array
    {
        // Setup handling multiple codes
        $codes = explode(',', $classCode);
        array_walk($codes, function (&$n): void {
            $n = "'" . $this->quote($n) . "'";
        });

        // prepare
        $stmt = $this->getConnection()->prepareQuery("
            SELECT
                FIRSTNAME,
                '' AS PREFERREDNAME,
                SURNAME AS LASTNAME,
                SCHOOLBOX_STUDENTCLASS.STUDENT_NUMBER AS SYNERGY_ID,
                YEAR_LEVEL AS YEARLEVEL,
                HOUSE,
                '' AS STUDENTMEDICALALERT,
                '' AS STUDENTSPECIALNEEDSFLAG,
                '' AS ENGLISHSECONDFLAG,
                '' AS STUDENTTUTORDESCRIPTION
            FROM  SCHOOLBOX_STUDENTCLASS
            LEFT JOIN SCHOOLBOX_STUDENTS ON SCHOOLBOX_STUDENTCLASS.STUDENT_NUMBER = SCHOOLBOX_STUDENTS.STUDENT_NUMBER
            WHERE SCHOOLBOX_STUDENTCLASS.CLASS_CODE IN (" . implode(',', $codes) . ")
            ORDER BY SURNAME ASC
        ");

        // retrieve
        $stmt->execute();

        // manipulate
        $students = [];

        $normaliser = new External\Edumate\FieldNormaliser();

        while ($student = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /* Other places expect fields to be in lowercase from this method,
             * building upon this db2 case nastiness. Start with lowercasing
             * everything first
             */
            $normalisedStudent = array_change_key_case($student, CASE_LOWER);

            /* Then camelCase a known whitelist
             */
            $normalisedStudent = $normaliser->normalise($normalisedStudent);

            /* Pile it into the stack
             */
            $students[$student['SYNERGY_ID']] = $normalisedStudent;
        }

        return array_values($students);
    }


    /**
     * @inheritdoc
     */
    public function getTeachers(string $classCode, ?Alaress_Date_Format $date = null): array
    {

        // Setup handling multiple codes
        $codes = explode(',', $classCode);
        array_walk($codes, function (&$n): void {
            $n = "'" . $this->quote($n) . "'";
        });


        // prepare
        $stmt = $this->getConnection()->prepareQuery("
            SELECT STAFF_NUMBER AS STAFFID
                FROM SCHOOLBOX_TEACHERCLASS
                WHERE CLASS_CODE IN (" . implode(',', $codes) . ")
        ");

        // retrieve
        $stmt->execute();

        // manipulate
        $teachers = array();
        while ($teacher = $stmt->fetchColumn()) {
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
