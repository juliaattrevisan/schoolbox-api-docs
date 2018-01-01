<?php

/**
 * External_Synergetic_Attendance
 *
 * Note that Synergetic's table uses "Seq" where we use "id"
 * They are synonymous in meaning (though sometimes disorientating)
 *
 * Reset sql for admin:
 *
 * DELETE FROM dbo.tAttendances WHERE AttendanceMasterSeq > 336;
 * DELETE FROM dbo.AttendanceMaster WHERE AttendanceMasterSeq > 336;
 *
 * SELECT * FROM dbo.AttendanceMaster ORDER BY AttendanceMasterSeq DESC;
 * SELECT * FROM dbo.tAttendances ORDER BY AttendanceMasterSeq DESC;
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Attendance_Synergetic_AttendanceMaster as AttendanceMaster;
use Schoolbox\External\Exception as ExternalException;

class External_Synergetic_Attendance implements External_AttendanceInterface
{
    use External_MssqlConnector;

    /** @var Attendance_Synergetic_AttendanceMaster $attendanceMaster An instance of Attendance_Synergetic_AttendanceMaster_Abstract */
    private $attendanceMaster = null;

    /**
     * Constructor
     *
     * @param array $class Result row from SynergyOne.dbo.SubjectClasses
     * @param Alaress_Date_Format $attendanceDate
     * @param string $attendanceDay
     * @param int $attendancePeriod
     * @param int $fileYear
     * @param int $fileSemester
     */
    public function __construct($class, Alaress_Date_Format $attendanceDate, $attendanceDay, $attendancePeriod, $fileYear, $fileSemester)
    {
        // This connects to the Synergetic database and stores an IConnection in `$this->connector` variable

        $this->setupDatabase();

        // Get the primary key associated with the selected class, date and period
        //
        // Find out whether the specified class, date and period have an existing AttendanceMaster record
        // If it does, the roll has been marked before and we only need to edit individual people
        //
        // Otherwise set up a new record for the AttendanceMaster table
        //
        // This can be encapsulated further in a Factory

        $attendanceMaster = new AttendanceMaster(
            $class,
            $attendanceDate,
            $attendanceDay,
            $attendancePeriod,
            $fileYear,
            $fileSemester,
            $this->connector,
            $_SESSION['sid']
        );

        $this->setAttendanceMaster($attendanceMaster);
    }

    /**
     * Get a list of attendees for a particular class, date and period
     *
     * @return array
     * @throws ExternalException
     */
    public function findAttendanceRecords()
    {
        $statement = $this->connector->executeStoredProcedure('spswkAttendances', [
            'wkAttendanceMasterSeq' => $this->getAttendanceMasterSeq(),
            'FillActualAbsenceColumnsFlag' => 1,
            'CheckPossibleNonAttendFlag' => 1,
            'CalculateTodaysAttendanceStringFlag' => 1
        ]);

        $result = $statement->fetchAll();

        if (count($result) > 0) {
            return $result;
        } else {
            throw new ExternalException("Unable to setup work space for attendances, cannot continue");
        }
    }

    /**
     * A list of options for the absence type drop-down
     *
     * @return array
     */
    public function findAbsenceDropdownOptions()
    {
        return $this->getSynergeticLookupOptions("dbo.luAbsenceType");
    }

    /**
     * A list of options for the attendance reason drop-down
     *
     * @return array
     */
    public function findReasonDropdownOptions()
    {
        return $this->getSynergeticLookupOptions("dbo.luAbsenceReason");
    }

    /**
     * Get Synergetic lookup table options with code appended in front of description for use in drop downs
     *
     * @param string $tableName
     * @return array
     */
    private function getSynergeticLookupOptions($tableName)
    {
        $statement = $this->connector->executeQuery("SELECT Code, Description FROM {$tableName} WHERE ActiveFlag = 1");
        $result = $statement->fetchAll();

        $returnArray = [];

        // Append code in front of description

        foreach ($result as $resultCurrent) {
            $return = [];
            $return['Code'] = trim($resultCurrent['Code']);

            if (!empty($return['Code'])) {
                $return['Description'] = "{$return['Code']} - {$resultCurrent['Description']}";
            } else {
                $return['Description'] = $resultCurrent['Description'];
            }

            $returnArray[] = $return;
        }

        return $returnArray;
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

    // Getters/Setters

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
}
