<?php

/**
 * External_Synergetic_Attendance
 *
 * Note that Synergetic's table uses "Seq" where we use "id"
 * They are synonymous in meaning (though sometimes disorientating)
 *
 * Reset sql for admin:
 *
 * DELETE FROM SynergyOne.dbo.tAttendances WHERE AttendanceMasterSeq > 336;
 * DELETE FROM SynergyOne.dbo.AttendanceMaster WHERE AttendanceMasterSeq > 336;
 *
 * SELECT * FROM SynergyOne.dbo.AttendanceMaster ORDER BY AttendanceMasterSeq DESC;
 * SELECT * FROM SynergyOne.dbo.tAttendances ORDER BY AttendanceMasterSeq DESC;
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class External_Synergetic_Attendance implements External_AttendanceInterface
{

    /**
     * The classCode whose attendances you are marking
     *
     * @var string
     */
    private $classCode = '';

    /**
     * The date in Y-m-d of the class
     *
     * @var string
     */
    private $attendanceDate = '';

    /**
     * The AttendancePeriod of the class
     *
     * @var int
     */
    private $attendancePeriod = -1;

    /**
     * An instance of Attendance_Synergetic_AttendanceMaster_Abstract
     *
     * @var null
     */
    private $attendanceMaster = null;

    /**
     * The attendance records for all students in the selected class, date and period
     *
     * @var array
     */
    private $allAttendanceRecords = array();

    /**
     * An MSSQL database instance
     *
     * @var \Database
     */
    private $database = null;

    /**
     * Usually from the session
     *
     * @var int
     */
    private $fileYear = -1;

    /**
     * Usually from the session
     *
     * @var int
     */
    private $fileSemester = -1;

    /**
     * Constructor
     *
     * @param $class
     * @param Alaress_Date_Format $attendanceDate
     * @param $attendancePeriod
     * @param $fileYear
     * @param $fileSemester
     * @param $database
     */
    public function __construct($class, Alaress_Date_Format $attendanceDate, $attendanceDay, $attendancePeriod, $fileYear, $fileSemester, $database)
    {
        $this->setClassCode($class['ClassCode']);
        $this->setAttendanceDate($attendanceDate->getDatabaseDate());
        $this->setAttendancePeriod($attendancePeriod);
        $this->setFileYear($fileYear);
        $this->setFileSemester($fileSemester);
        $this->setDatabase($database);

        /* Get the primary key associated with the selected class, date and period
          */

        /* Find out whether the specified class, date and period have an existing AttendanceMaster record
         * If it does, the roll has been marked before and we only need to edit individual people
         *
         * Otherwise set up a new record for the AttendanceMaster table
         *
         * This can be encapsulated further in a Factory
        */

        $attendanceMaster = new Attendance_Synergetic_AttendanceMaster($class, $attendanceDate, $attendanceDay, $attendancePeriod, $fileYear, $fileSemester, $database);
        $this->setAttendanceMaster($attendanceMaster);

        $attendanceMaster->setupWork();

        $this->setup();
    }

    /**
     * Hook method
     */
    private function setup()
    {
    }


    /**
     * Get a list of attendees for a particular class, date and period
     *
     * @return mixed
     */
    public function findAttendanceRecords()
    {
        $result = $this->getDatabase()->executeProcedure(
            'spswkAttendances_ws', array(
                array('@wkAttendanceMasterSeq', $this->getAttendanceMasterSeq(), SQLINT4),
                array('@FillActualAbsenceColumnsFlag', 1, SQLINT4),
                array('@CheckPossibleNonAttendFlag', 1, SQLINT4),
                array('@CalculateTodaysAttendanceStringFlag', 1, SQLINT4),
            )
        );

        if ($result['success'] == 1) {
            $this->setAllAttendanceRecords($result['result']);
        }

        return $this->getAllAttendanceRecords();
    }

    /**
     * A list of options for the Absence dropdown
     *
     * @return mixed
     */
    public function findAbsenceDropdownOptions()
    {
        $query = "
            SELECT Code, Description
            FROM SynergyOne.dbo.luAbsenceType
            WHERE ActiveFlag = 1
        ";

        return $this->getDatabase()->getAll($query);
    }

    /**
     * A list of options for the Reason dropdown
     *
     * @return mixed
     */
    public function findReasonDropdownOptions()
    {
        $query = "
            SELECT Code, Description
            FROM SynergyOne.dbo.luAbsenceReason
            WHERE ActiveFlag = 1
        ";

        return $this->getDatabase()->getAll($query);
    }

    /**
     * Determine if there are attendance records for this particular
     * class, date and period by checking if the AttendanceMasterSeq
     * value was found
     *
     * @return bool
     */
    public function hasAttendanceMasterSeq()
    {
        return $this->getAttendanceMaster()->hasAttendanceMasterSeq();
    }

    /* Getters/Setters
     */

    /**
     * Set attendance date
     *
     * @param string $attendanceDate
     */
    private function setAttendanceDate($attendanceDate)
    {
        $this->attendanceDate = $attendanceDate;
    }

    /**
     * Get attendance date
     *
     * @return string
     */
    private function getAttendanceDate()
    {
        return $this->attendanceDate;
    }

    /**
     * Set attendance period
     *
     * @param int $attendancePeriod
     */
    private function setAttendancePeriod($attendancePeriod)
    {
        $this->attendancePeriod = $attendancePeriod;
    }

    /**
     * Get attendance period
     *
     * @return int
     */
    private function getAttendancePeriod()
    {
        return $this->attendancePeriod;
    }

    /**
     * Set class code
     *
     * @param string $classCode
     */
    private function setClassCode($classCode)
    {
        $this->classCode = $classCode;
    }

    /**
     * Get class code
     *
     * @return string
     */
    private function getClassCode()
    {
        return $this->classCode;
    }

    /**
     * Set database
     *
     * @param \Database $database
     */
    private function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * Get database
     *
     * @return \Database
     */
    private function getDatabase()
    {
        return $this->database;
    }

    /**
     * Delegation method
     *
     * Get attendance master seq
     *
     * @return int
     */
    private function getAttendanceMasterSeq()
    {
        return $this->getAttendanceMaster()->getAttendanceMasterSeq();
    }

    /**
     * Set attendance records for a particular class, date and period
     *
     * @param array $allAttendanceRecords
     */
    private function setAllAttendanceRecords($allAttendanceRecords)
    {
        $this->allAttendanceRecords = $allAttendanceRecords;
    }

    /**
     * Get all attendance records for a particular class, date and period
     *
     * @return array
     */
    private function getAllAttendanceRecords()
    {
        return $this->allAttendanceRecords;
    }

    /**
     * Set attendance master
     *
     * @param null $attendanceMaster
     */
    private function setAttendanceMaster($attendanceMaster)
    {
        $this->attendanceMaster = $attendanceMaster;
    }

    /**
     * Get attendance master
     *
     * @return Attendance_Synergetic_AttendanceMaster
     */
    public function getAttendanceMaster()
    {
        return $this->attendanceMaster;
    }

    /**
     * Set file year
     *
     * @param int $fileYear
     */
    private function setFileYear($fileYear)
    {
        $this->fileYear = $fileYear;
    }

    /**
     * Get file year
     *
     * @return int
     */
    private function getFileYear()
    {
        return $this->fileYear;
    }

    /**
     * Set file semester
     *
     * @param int $fileSemester
     */
    private function setFileSemester($fileSemester)
    {
        $this->fileSemester = $fileSemester;
    }

    /**
     * Get file semester
     *
     * @return int
     */
    private function getFileSemester()
    {
        return $this->fileSemester;
    }
}
