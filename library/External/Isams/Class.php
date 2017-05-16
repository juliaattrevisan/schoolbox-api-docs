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
    function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        $this->externalUser = new External_User('isams');
    }

    /**
     * Get all student in class
     * @param $subCode
     * @return array
     */
    public function getStudents($subCode)
    {
        $classQuery = $this->getClassCodeQuery($subCode);

        $query = "
            SELECT    V_Student.StudentSurname      as lastname, 
                      V_Student.StudentFirstname    as firstname, 
                      V_Student.Grade               as yearlevel, 
                      ''                  as house, 
                      V_Student.MedicalFlag         as StudentMedicalAlert, 
                      V_Student.txtadditionalHealth as StudentSpecialNeedsFlag, 
                      V_Student.StudentID as synergy_id, 
                      null                as ClassCampus 
            FROM      V_Student 
            JOIN      V_Class ON V_Class.StudentID = V_Student.StudentID
            WHERE     $classQuery
        ";

        $rows = $this->getDatabase()->getAll($query);

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
            $code = "'" . $this->database->quote($code) . "'";
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

        $rows = $this->getDatabase()->getAll($query);

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
     * @param $database
     */
    private function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return MSSQL
     */
    private function getDatabase()
    {
        return $this->database;
    }
}

