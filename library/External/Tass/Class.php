<?php
/**
 * This file provides access to TASS class info
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class External_Tass_Class implements External_ClassInterface {
    /**
     * @var MSSQL
     */
    private $database;

    /**
     * Class code delimiter
     */
    private const CLASS_CODE_DELIMITER = '-';

    /**
     * @var External_UserInterface
     */
    private $externalUser;

    function __construct () {
        $this->setDatabase(MSSQL::getInstance());
        $this->externalUser = new External_User('tass');
    }


    /**
     * @inheritdoc
     */
    public function getStudents(string $classCode): array
    {
        if (!$classCode) {
            /* Dont bother if you got no code */
            return [];
        }

        $classQuery = '';
        if(stristr($classCode, ',')) {
            $subCodeArray = explode(',',$classCode);
            foreach($subCodeArray as $code) {
                list($year, $code, $class) = self::parseClassCode($code);
                $yearQuery = $year !== '' ?  "student.year_grp = '$year' AND" : '';

                $classQuery .= " ( $yearQuery studsub.sub_code = '$code' AND studsub.class = '$class' ) OR ";
            }
            $classQuery .= '1=0';
        }
        else {
            list($year, $code, $class) = self::parseClassCode($classCode);
            $yearQuery = $year !== '' ?  "student.year_grp = '$year' AND" : '';

            $classQuery = " $yearQuery studsub.sub_code = '$code' AND studsub.class = '$class' ";
        }


        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester());
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear());
        $query = "
            SELECT DISTINCT 
                studsub.stud_code as synergy_id, 
                student.preferred_name as firstname, 
                student.surname as lastname, 
                student.year_grp as yearlevel, 
                student.house, 
                '' AS StudentMedicalAlert, 
                '' AS EnglishSecondFlag,
                '' AS StudentSpecialNeedsFlag, 
                '' as StudentTutorDescription
            FROM studsub
            JOIN student ON student.stud_code = studsub.stud_code AND student.cmpy_code = studsub.cmpy_code
            WHERE semester = '{$fileSemester}'
                AND yr_study = '{$fileYear}'
                AND next_yr_ind != 'F'
                AND ( $classQuery )
            ORDER BY lastname, firstname";

        return $this->getDatabase()->trimAll($this->getDatabase()->getAll($query));
    }


    /**
     * @inheritdoc
     */
    public function getTeachers(string $classCode): array
    {
        if (!$classCode) {
            /* Dont bother if you got no code */
            return [];
        }

        $classQuery = '';
        if(stristr($classCode, ',')) {
            $subCodeArray = explode(',',$classCode);
            foreach($subCodeArray as $code) {
                list($year, $code, $class) = self::parseClassCode($code);
                $yearQuery = $year !== '' ?  "(year_grp = '$year' OR year_grp IS NULL) AND" : '';


                $classQuery .= " ( $yearQuery sub_code = '$code' AND class = '$class' ) OR ";
            }
            $classQuery .= '1=0';
        }
        else {
            list($year, $code, $class) = self::parseClassCode($classCode);
            $yearQuery = $year !== '' ?  "(year_grp = '$year' OR year_grp IS NULL) AND" : '';

            $classQuery = " $yearQuery sub_code = '$code' AND class = '$class' ";
        }


        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester());
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear());
        $query = "SELECT DISTINCT tch_code FROM tchsub 
                    WHERE semester = '{$fileSemester}' AND year_num = '{$fileYear}'
                    AND ( $classQuery )";

        $statement = $this->getDatabase()->query($query);
        $teachers = [];
        while ($synrow = $statement->fetch()) {
            $teachers[] = $synrow['tch_code'];
        }

        return array_unique($teachers);
    }

    private function setDatabase ($database) {
        $this->database = $database;
    }

    private function getDatabase () {
        return $this->database;
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

    /**
     * Parse year, code, class from the given class code.
     * @param string $classCode
     * @return array
     */
    private static function parseClassCode(string $classCode): array
    {
        $parts = explode(self::CLASS_CODE_DELIMITER, $classCode);

        if (\count($parts) === 3) {
            return $parts;
        }

        if (\count($parts) === 4 && $parts[0] === '' && preg_match('/[0-9]+/', $parts[1])) {
            return [self::CLASS_CODE_DELIMITER . $parts[1], $parts[2], $parts[3]];
        }

        // shouldn't get here, but just in case.
        return $parts;
    }
}
