<?php

use Alaress\Database\Connector\IConnector;
use Schoolbox\Database\Factory;

/**
 * This file provides access to TASS class info
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class External_Tass_Class implements External_ClassInterface
{
    /**
     * @var IConnector
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

    public function __construct()
    {
        $this->database     = Factory::getInstanceExternal();
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
        if (stristr($classCode, ',')) {
            $subCodeArray = explode(',', $classCode);
            foreach ($subCodeArray as $code) {
                [$year, $code, $class] = self::parseClassCode($code);
                $yearQuery = $year !== '' ? "student.year_grp = '$year' AND" : '';

                $classQuery .= " ( $yearQuery studsub.sub_code = '$code' AND studsub.class = '$class' ) OR ";
            }
            $classQuery .= '1=0';
        } else {
            [$year, $code, $class] = self::parseClassCode($classCode);
            $yearQuery = $year !== '' ? "student.year_grp = '$year' AND" : '';

            $classQuery = " $yearQuery studsub.sub_code = '$code' AND studsub.class = '$class' ";
        }


        $fileSemester = $this->database->legacyQuote($this->externalUser->getCurrentSemester());
        $fileYear     = $this->database->legacyQuote($this->externalUser->getCurrentYear());
        $query        = "
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
            WHERE semester = {$fileSemester}
                AND yr_study = {$fileYear}
                AND next_yr_ind != 'F'
                AND student.doe <= CAST(GETDATE() AS DATE)
                AND (student.dol IS NULL OR student.dol >= CAST(GETDATE() AS DATE))
                AND (studsub.from_date IS NULL OR studsub.from_date <= CAST(GETDATE() AS DATE))
                AND (studsub.to_date IS NULL OR studsub.to_date >= CAST(GETDATE() AS DATE))
                AND ( $classQuery )
            ORDER BY lastname, firstname";

        return $this->database->trimAll($this->database->getAll($query));
    }


    /**
     * @inheritdoc
     */
    public function getTeachers(string $classCode, Alaress_Date_Format $date = null): array
    {
        if (!$classCode) {
            /* Dont bother if you got no code */
            return [];
        }

        $classQueries = [];
        $subCodeArray = explode(',', $classCode);
        foreach ($subCodeArray as $code) {
            [$year, $code, $class] = self::parseClassCode($code);
            // tchsub.year_grp is smallint
            $yearQuery      = is_numeric($year) ? "(year_grp = '$year' OR year_grp IS NULL) AND" : '';
            $classQueries[] = " ( $yearQuery sub_code = '$code' AND class = '$class' ) ";
        }
        $classQuery = implode(' OR ', $classQueries);

        $fileSemester = $this->database->legacyQuote($this->externalUser->getCurrentSemester());
        $fileYear     = $this->database->legacyQuote($this->externalUser->getCurrentYear());
        $query        = "SELECT DISTINCT tch_code FROM tchsub
                    WHERE semester = {$fileSemester} AND year_num = {$fileYear}
                    AND ( $classQuery )";

        $statement = $this->database->query($query);
        $teachers  = [];
        while ($synrow = $statement->fetch()) {
            $teachers[] = $synrow['tch_code'];
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

    /**
     * Parse year, code, class from the given class code.
     * @param string $classCode
     * @return array
     */
    private static function parseClassCode(string $classCode): array
    {
        $parts = explode(self::CLASS_CODE_DELIMITER, $classCode);

        if (\count($parts) === 3) {
            // Three parts: this is what was expected
            [$year, $code, $class] = $parts;
        } elseif (\count($parts) === 4 && $parts[0] === '' && preg_match('/[0-9]+/', $parts[1])) {
            // Four parts: that can happen, because the year can be negative
            [$year, $code, $class] = [self::CLASS_CODE_DELIMITER . $parts[1], $parts[2], $parts[3]];
        } else {
            // Some other number of parts: shouldn't get here?
            // Should this be an error? I think so but pretend it's something
            $class = array_pop($parts);
            $code  = array_pop($parts) ?? '';
            $year  = array_pop($parts) ?? '';
        }

        // Ensure year is an int, or an empty string if not set
        $year = filter_var($year, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        return [
            isset($year) ? (string)$year : '',
            $code,
            $class,
        ];
    }
}
