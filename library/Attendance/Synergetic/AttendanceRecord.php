<?php

/**
 * Attendance_Synergetic_AttendanceRecord
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Attendance_Synergetic_AttendanceRecord
{

    /**
     * An MSSQL database instance
     *
     * @var \Database
     */
    protected $database = null;

    /**
     * @param $database
     */
    public function __construct($database)
    {
        $this->setDatabase($database);
    }

    /**
     * Update an attendance record
     *
     * @param $data
     *
     * @return \PDOStatement
     */
    public function update($data)
    {

        if (isset($data['ID']) && isset($data['AttendanceMasterSeq'])) {

            $userID = $data['ID'];
            $AttendanceMasterSeq = $data['AttendanceMasterSeq'];
            unset($data['ID']);
            unset($data['AttendanceMasterSeq']);

            $query = "UPDATE SynergyOne.dbo.wkAttendances SET";

            /* Set through all the data contained in array and set it */
            foreach ($data as $key => $value) {
                $query .= " $key = '$value',";
            }

            $query = substr($query, 0, -1);

            /* Set based on two key values sequence and user id */
            $query .= "
                WHERE wkAttendanceMasterSeq = '$AttendanceMasterSeq'
                AND	ID = '$userID'
            ";
            return $this->getDatabase()->query($query);
        } else {
            return false;
        }
    }

    /* Getters/Setters
     */

    /**
     * Set database
     *
     * @param \Database $database
     */
    protected function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * Get database
     *
     * @return \Database
     */
    protected function getDatabase()
    {
        return $this->database;
    }
}
