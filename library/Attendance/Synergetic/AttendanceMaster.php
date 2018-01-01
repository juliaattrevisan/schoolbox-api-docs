<?php

/**
 * Attendance_Synergetic_AttendanceMaster
 *
 * Each instance of this represents a new row in the AttendanceMaster table.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

class Attendance_Synergetic_AttendanceMaster
{
    /** @var IConnector $connector Database connector */
    protected $connector;

    /* @var string $classCode The external class code whose attendances you are marking */
    protected $classCode = '';

    /** @var string The external campus code whose attendances you are marking */
    protected $classCampus = '';

    /** @var string $classFileType The external file type of class (defined in SynergyOne.dbo.luFileYear) */
    protected $classFileType = 'A';

    /** @var string $attendanceDate The date in `Y-m-d` of the class */
    protected $attendanceDate = '';

    /** @var int $attendancePeriod The attendance period of the class */
    protected $attendancePeriod = -1;

    /** @var int $fileYear From the session, usually; but you can specify your own */
    protected $fileYear = -1;

    /** @var int $fileSemester From the session, usually; but you can specify your own */
    protected $fileSemester = -1;

    /** @var array $data All the keys will be put in here to make it compatible with the database query interface */
    protected $data = [];

    /** @var int $attendanceMasterSeq The primary key associated with the selected class, date and period used in dbo.AttendanceMaster */
    protected $attendanceMasterSeq = -1;

    /**
     * Constructor
     *
     * @param array $class Result row from SynergyOne.dbo.SubjectClasses
     * @param Alaress_Date_Format $attendanceDate
     * @param string $attendanceDay
     * @param int $attendancePeriod
     * @param int $fileYear
     * @param int $fileSemester
     * @param IConnector $connector
     */
    public function __construct($class, $attendanceDate, $attendanceDay, $attendancePeriod, $fileYear, $fileSemester, $connector, $staffId)
    {
        // Store database connection

        $this->connector = $connector;

        // Store the stuff we'll use again

        $this->setClassCampus($class['ClassCampus']);
        $this->setClassCode($class['ClassCode']);
        $this->setClassFileType($class['FileType']);
        $this->setAttendancePeriod($attendancePeriod);
        $this->setFileYear($fileYear);
        $this->setFileSemester($fileSemester);

        // Get the attendance master sequence number

        $statement = $this->connector->prepareStoredProcedure('spiwkAttendances');

        $statement->bindInputs([
            'FileType' => $this->getClassFileType(),
            'FileYear' => $this->getFileYear(),
            'FileSemester' => $this->getFileSemester(),
            'ClassCampus' => $class['ClassCampus'],
            'ClassCode' => $this->getClassCode(),
            'AttendanceDate' => $attendanceDate->getDatabaseDate() . ' 00:00:00.000',
            'AttendancePeriod' => $this->getAttendancePeriod(),
            'AttendanceDayNumber' => $attendanceDay,
            'StaffID' => $staffId,
            'AttendedFlag' => 1,
            'TimeTableGroup' => $this->getTimetableGroup(),
            'SynWebFlag' => 1,
        ]);

        // Bind output parameters for procedure

        $attendanceMasterSeq = null;

        $outputs = [];
        $outputs['wkAttendanceMasterSeq'] = [
            'value' => &$attendanceMasterSeq,
            'type' => PDO::PARAM_INT,
            'length' => 4
        ];

        $statement->bindOutputs($outputs);

        $result = $statement->execute();
        $statement->nextRowset();

        // Don't know if all these are required. But it is possible for the query to return an unknown
        // value for AttendanceMasterSeq due to an error handler in the stored procedure.

        if ($result && !empty($attendanceMasterSeq) && is_numeric($attendanceMasterSeq) && $attendanceMasterSeq > 0) {
            $this->setAttendanceMasterSeq($attendanceMasterSeq);
        } else {
            throw new SchoolboxException('Cannot setup attendance record. Unable to retrieve attendance sequence.');
        }        
    }

    /**
     * Find the TimetableGroup value for a particular class, date and period
     * and for a particular FileYear and FileSemester set of values.
     *
     * @return string|bool
     */
    protected function getTimetableGroup()
    {
        $query = "
			SELECT  *
			FROM    dbo.Timetable
			WHERE   FileType = :fileType
			AND     FileYear = :fileYear
			AND     FileSemester = :fileSemester
			AND     PeriodNumber = :periodNumber
			AND     ClassCode = :classCode
			AND     ClassCampus = :classCampus
		";
        
        $statement = $this->connector->executeQuery($query, [
            'fileType'      => $this->getClassFileType(),
            'fileYear'      => $this->getFileYear(),
            'fileSemester'  => $this->getFileSemester(),
            'periodNumber'  => $this->getAttendancePeriod(),
            'classCode'     => $this->getClassCode(),
            'classCampus'   => $this->getClassCampus()
        ]);

        $result = $statement->fetch();

        if (is_array($result) && isset($result['TimetableGroup'])) {
            return $result['TimetableGroup'];
        }

        return false;
    }

    /**
     * Function to transfer data from work table to live tables, this
     * is called once the update has been done and the user wants to
     * save data
     *
     * @return bool
     */
    public function save()
    {
        $statement = $this->connector->prepareStoredProcedure('sptwkAttendances');

        $statement->bindInputs([
            'wkAttendanceMasterSeq' => $this->getAttendanceMasterSeq(),
            'HoldFlag' => 0
        ]);

        $attendanceMasterSeq = null;

        $outputs = [];
        $outputs['AttendanceMasterSeq'] = [
            'value' => &$attendanceMasterSeq,
            'type' => PDO::PARAM_INT,
            'length' => 4
        ];

        $statement->bindOutputs($outputs);

        return $statement->execute();
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
        return $this->getAttendanceMasterSeq() > 0;
    }

    // Getters & Setters

    /**
     * @return int
     */
    protected function getAttendancePeriod()
    {
        return $this->attendancePeriod;
    }

    /**
     * @param int $attendancePeriod
     * @return $this
     */
    protected function setAttendancePeriod($attendancePeriod)
    {
        $this->attendancePeriod = $attendancePeriod;
        return $this;
    }

    /**
     * @return string
     */
    protected function getClassCode()
    {
        return $this->classCode;
    }

    /**
     * @param string $classCode
     * @return $this
     */
    protected function setClassCode($classCode)
    {
        $this->classCode = $classCode;
        return $this;
    }

    /**
     * @return int
     */
    protected function getFileSemester()
    {
        return $this->fileSemester;
    }

    /**
     * @param int $fileSemester
     * @return $this
     */
    protected function setFileSemester($fileSemester)
    {
        $this->fileSemester = $fileSemester;
        return $this;
    }

    /**
     * @return int
     */
    protected function getFileYear()
    {
        return $this->fileYear;
    }

    /**
     * @param int $fileYear
     * @return $this
     */
    protected function setFileYear($fileYear)
    {
        $this->fileYear = $fileYear;
        return $this;
    }

    /**
     * @return int
     */
    public function getAttendanceMasterSeq()
    {
        return $this->attendanceMasterSeq;
    }

    /**
     * @param int $attendanceMasterSeq
     * @return $this
     */
    protected function setAttendanceMasterSeq($attendanceMasterSeq)
    {
        $this->attendanceMasterSeq = $attendanceMasterSeq;
        return $this;
    }

    /**
     * @return string
     */
    public function getClassCampus()
    {
        return $this->classCampus;
    }

    /**
     * @param string $classCampus
     * @return $this
     */
    public function setClassCampus($classCampus)
    {
        $this->classCampus = $classCampus;
        return $this;
    }

    /**
     * @return string
     */
    public function getClassFileType()
    {
        return $this->classFileType;
    }

    /**
     * @param  string $classFileType
     * @return $this
     */
    public function setClassFileType($classFileType)
    {
        $this->classFileType = $classFileType;
        return $this;
    }
}
