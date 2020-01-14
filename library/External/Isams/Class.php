<?php
/**
 * Isams Class Object
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 * Uncle iSam?
 *    _..._
 *   |||||||
 *   |||||||
 *  _|*****|_
 * ( '-...-' )
 * {`;-----;`}
 * {(| a  a|)}
 *  \|  _\ |/
 *   \  -  /
 *    '{_}`
 */

class External_Isams_Class implements External_ClassInterface
{
    use External_MssqlConnector;

    /**
     * @var MSSQL $database
     */
    private $database;

    /**
     * @var External_UserInterface
     */
    private $externalUser;

    /**
     * External_Doublefirst_Class constructor.
     */
    public function __construct()
    {
        $this->setupDatabase();
        $this->externalUser = new External_User('isams');
    }

    /**
     * @inheritdoc
     */
    public function getStudents(string $classCode): array
    {
        if (empty($classCode)) {
            return [];
        }

        $classQuery = $this->getClassCodeQuery($classCode);

        $query = "
            SELECT    V_Student.StudentSurname      AS lastname,
                      V_Student.StudentFirstname    AS firstname,
                      V_Student.Grade               AS yearlevel,
                      ''                            AS house,
                      V_Student.MedicalFlag         AS StudentMedicalAlert,
                      V_Student.txtadditionalHealth AS StudentSpecialNeedsFlag,
                      ''                            AS EnglishSecondFlag,
                      ''                            AS StudentTutorDescription,
                      V_Student.StudentID           AS synergy_id
            FROM      V_Student
            JOIN      V_Class ON V_Class.StudentID = V_Student.StudentID
            WHERE     $classQuery
        ";

        $rows = $this->getConnector()->executeQuery($query)->fetchAll();

        if ($rows) {
            return $rows;
        }

        return [];
    }

    /**
     * Gets the class code part of a query.
     *
     * @param  string $subCode
     * @return string
     */
    protected function getClassCodeQuery($subCode)
    {
        $subCodeArray = array_filter(explode(",", $subCode));

        foreach ($subCodeArray as &$code) {
            $code = "'" . $this->quote($code) . "'";
        }

        return "V_Class.classcode IN (" . implode($subCodeArray, ',') . ")";
    }

    /**
     * @inheritdoc
     */
    public function getTeachers(string $classCode, Alaress_Date_Format $date = null): array
    {
        if (empty($classCode)) {
            return [];
        }

        $classQuery = $this->getClassCodeQuery($classCode);

        $query =
            "
            SELECT DISTINCT   Tutor1ID AS tutorID
            FROM              V_Class
            WHERE             $classQuery
            UNION
            SELECT DISTINCT   Tutor2ID AS tutorID
            FROM              V_Class
            WHERE             NULLIF(LTRIM(RTRIM(Tutor2name)), '') IS NOT NULL AND
                              $classQuery
        ";

        $rows = $this->getConnector()->executeQuery($query)->fetchAll();

        if (!$rows) {
            return [];
        }

        $teachers = array();

        foreach ($rows as $row) {
            $teachers[] = $row['tutorID'];
        }

        return $teachers;
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

