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
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Attendance_Synergetic_AttendanceMaster as AttendanceMaster;
use Schoolbox\External\Exception as ExternalException;

class External_Synergetic_Attendance implements External_AttendanceInterface
{
    use External_MssqlConnector;

    /**
     * This is the value hardcoded by the synergetic stored proc
     */
    private const SPECIAL_NEEDS_SYNERGETIC_STOR_PROC = 'Special Needs';

    /** @var Attendance_Synergetic_AttendanceMaster $attendanceMaster An instance of Attendance_Synergetic_AttendanceMaster_Abstract */
    private $attendanceMaster;


    /**
     * Constructor
     *
     * @param array               $class Result row from SynergyOne.dbo.SubjectClasses
     * @param Alaress_Date_Format $attendanceDate
     * @param string              $attendanceDay
     * @param int                 $attendancePeriod
     * @param int                 $fileYear
     * @param int                 $fileSemester
     * @param int                 $staffId
     * @param string              $timetableGroup
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function __construct($class, Alaress_Date_Format $attendanceDate, $attendanceDay, $attendancePeriod, $fileYear, $fileSemester, $staffId, $timetableGroup)
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
            $staffId,
            $timetableGroup
        );

        $this->attendanceMaster = $attendanceMaster;
    }

    /**
     * @inheritDoc
     * @throws ExternalException
     */
    public function findAttendanceRecords(): array
    {
        $statement = $this->connector->executeStoredProcedure('spswkAttendances', [
            'wkAttendanceMasterSeq' => $this->attendanceMaster->getAttendanceMasterSeq(),
            'FillActualAbsenceColumnsFlag' => 1,
            'CheckPossibleNonAttendFlag' => 1,
            'CalculateTodaysAttendanceStringFlag' => 1
        ]);

        $result = $statement->fetchAll();

        if (count($result) > 0) {
            array_walk($result, function (&$val) {
                $val['SpecialNeedsDescription'] = preg_replace('/^' . self::SPECIAL_NEEDS_SYNERGETIC_STOR_PROC . '$/', '', $val['SpecialNeedsDescription']);
            });
            return $result;
        }

        throw new ExternalException("Unable to setup work space for attendances, cannot continue");
    }


    /**
     * @inheritDoc
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function findAbsenceDropdownOptions(): array
    {
        $statement = $this->connector->executeQuery("SELECT Code, Description FROM dbo.luAbsenceType WHERE ActiveFlag = 1");
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
     * @inheritDoc
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function findReasonDropdownOptions(): array
    {
        $statement = $this->connector->executeQuery("SELECT AbsenceTypeCode, Code, Description FROM dbo.luAbsenceReason WHERE ActiveFlag = 1 ORDER BY Code");
        $result = $statement->fetchAll();

        $returnArray = [];

        // Append code in front of description

        foreach ($result as $resultCurrent) {
            $return = [];
            $return['AbsenceTypeCode'] = trim($resultCurrent['AbsenceTypeCode']);
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

    // Getters/Setters

    /**
     * Get attendance master
     *
     * @return Attendance_Synergetic_AttendanceMaster
     */
    public function getAttendanceMaster(): Attendance_Synergetic_AttendanceMaster
    {
        return $this->attendanceMaster;
    }
}
