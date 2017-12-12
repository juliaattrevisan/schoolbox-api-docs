<?php
/**
 * Isams Class Object
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
     * Get all student in class
     * @param $subCode
     * @return array
     */
    public function getStudents($subCode)
    {
        if (empty($subCode)) {
            return [];
        }

        $classQuery = $this->getClassCodeQuery($subCode);

        $query = "
            SELECT    V_Student.StudentSurname      AS lastname, 
                      V_Student.StudentFirstname    AS firstname, 
                      V_Student.Grade               AS yearlevel, 
                      ''                            AS house, 
                      V_Student.MedicalFlag         AS StudentMedicalAlert, 
                      V_Student.txtadditionalHealth AS StudentSpecialNeedsFlag, 
                      ''                            AS StudentTutorDescription,
                      V_Student.StudentID           AS synergy_id, 
                      null                          AS ClassCampus 
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
     * Retrieve teacher external IDs for a particular class (great function naming)
     * @param $subCode
     * @param null $user
     * @return array
     */
    public function getTeacherName($subCode, $user = null)
    {
        if (empty($subCode)) {
            return [];
        }

        $classQuery = $this->getClassCodeQuery($subCode);

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
}

