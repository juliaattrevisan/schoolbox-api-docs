<?php
/**
 * PCSchools class connector
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Pcschools_Class implements External_ClassInterface
{
    /**
     * @var MSSQL
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
    }

    /**
     * Get a list of students in a particular $classCode
     * lastname,
     * firstname,
     * yearlevel,
     * StudentHouseDescription as house,
     * StudentMedicalAlert,
     * StudentSpecialNeedsFlag,
     * synergy_id,
     * ClassCampus
     *
     * @param $classCode
     *
     * @return array
     */
    public function getStudents($classCode)
    {
        // TODO: Implement getStudents() method.
    }

    /**
     * Returns a list of *THE IDs OF* teachers that are associated
     * with a class (method is misnamed)
     *
     * @param $classCode
     *
     * @return array
     */
    public function getTeacherName($classCode)
    {
        // TODO: Implement getTeacherName() method.
    }

    /**
     * @return MSSQL
     */
    private function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param  MSSQL $database
     *
     * @return $this
     */
    private function setDatabase($database)
    {
        $this->database = $database;
        return $this;
    }
}
