<?php

/**
 * Attendance_Synergetic_AttendanceMaster
 *
 * Each instance of this represents a new row in the AttendanceMaster table.
 * Default values are defined in setup() and written to the db via insertStub()
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class Attendance_Synergetic_AttendanceMaster
{

    /**
     * The class
     *
     * @var null
     */
    protected $class = null;

    /**
     * The classCode whose attendances you are marking
     *
     * @var string
     */
    protected $classCode = '';

    /**
     * The classCampus whose attendances you are marking
     *
     * @var string
     */
    protected $classCampus = '';

    /**
     * The date in Y-m-d of the class
     *
     * @var string
     */
    protected $attendanceDate = '';

    /**
     * The day number of the class
     *
     * @var string
     */
    protected $attendanceDay = '';

    /**
     * The AttendancePeriod of the class
     *
     * @var int
     */
    protected $attendancePeriod = -1;

    /**
     * From the session, usually; but you can specify your own
     *
     * @var int
     */
    protected $fileYear = -1;

    /**
     * From the session, usually; but you can specify your own
     *
     * @var int
     */
    protected $fileSemester = -1;

    /**
     * An MSSQL database instance
     *
     * @var \Database
     */
    protected $database = null;

    /**
     * All the keys will be put in here to make it compatible with
     * the database query interface
     *
     * @var array
     */
    protected $data = array();

    /**
     * The primary key associated with the selected class, date and period
     * This is used in SynergyOne.dbo.AttendanceMaster
     *
     * @var int
     */
    protected $attendanceMasterSeq = -1;

    /**
     * Constructor
     *
     * @param $class
     * @param $attendanceDate
     * @param $attendanceDay
     * @param $attendancePeriod
     * @param $fileYear
     * @param $fileSemester
     * @param $database
     */
    public function __construct($class, $attendanceDate, $attendanceDay, $attendancePeriod, $fileYear, $fileSemester, $database)
    {
        $this->setClass($class);
        $this->setClassCampus($class['ClassCampus']);
        $this->setClassCode($class['ClassCode']);
        $this->setAttendanceDate($attendanceDate->getDatabaseDate());
        $this->setAttendanceDay($attendanceDay);
        $this->setAttendancePeriod($attendancePeriod);
        $this->setFileYear($fileYear);
        $this->setFileSemester($fileSemester);
        $this->setDatabase($database);

        $this->setup();
    }

    protected function setup()
    {
        $class = $this->getClass();

        /* Default settings for a new AttendanceMaster record
         */
        $data = array(
            'CreatedDate'               => 'GETDATE()',
            'CreatedByID'               => $_SESSION['sid'],
            'ModifiedDate'              => 'GETDATE()',
            'ModifiedByID'              => $_SESSION['sid'],
            'FileType'                  => 'A',
            'FileYear'                  => $this->getFileYear(),
            'FileSemester'              => $this->getFileSemester(),
            'ClassCampus'               => $class['ClassCampus'],
            'ClassCode'                 => $this->getClassCode(),
            'StaffID'                   => $_SESSION['sid'],
            'AttendanceDate'            => $this->getAttendanceDate() . ' 00:00:00.000',
            'AttendancePeriod'          => $this->getAttendancePeriod(),

            /* Where do these come from?
             */
            'AttendanceDateTimeFrom'    => null,
            'AttendanceDateTimeTo'      => null,

            /* These two methods can be refactored, they are virtually identical
             */
            'AttendanceDayNumber'       => $this->getTimetableDayNumber(),
            'TimetableGroup'            => $this->getTimetableGroup(),

            'ClassCancelledFlag'        => 0,
            'AttendanceOfficerModeFlag' => 0,
            'SystemProcessNumber'       => 0,
        );

        $this->setData($data);
    }

    /**
     * Find the DayNumber value for a particular class, date and period
     * and for a particular FileYear and FileSemester set of values.
     *
     * @return mixed
     */
    protected function getTimetableDayNumber()
    {
        return $this->attendanceDay;
    }

    /**
     * Find the TimetableGroup value for a particular class, date and period
     * and for a particular FileYear and FileSemester set of values.
     *
     * @return mixed
     */
    protected function getTimetableGroup()
    {
        $query = "
			SELECT *
			FROM SynergyOne.dbo.Timetable
			WHERE FileType = 'A'
			AND FileYear = '{$this->getFileYear()}'
			AND FileSemester = '{$this->getFileSemester()}'
			AND PeriodNumber = '{$this->getAttendancePeriod()}'
			AND ClassCode = '{$this->getClassCode()}'
			AND ClassCampus = '{$this->getClassCampus()}'
		";

        $result = $this->getDatabase()->getRow($query);

        if (is_array($result)) {
            return $result['TimetableGroup'];
        }

        return false;
    }

    /**
     * Insert that stub row
     */
    public function setupWork()
    {
        $data = $this->getData();
        $value = array(
            array('@FileType', $data['FileType'], SQLVARCHAR),
            array('@FileYear', $data['FileYear'], SQLINT4),
            array('@FileSemester', $data['FileSemester'], SQLINT4),
            array('@ClassCampus', $data['ClassCampus'], SQLVARCHAR),
            array('@ClassCode', $data['ClassCode'], SQLVARCHAR),
            array('@AttendanceDate', $data['AttendanceDate'], SQLVARCHAR),
            array('@AttendancePeriod', $data['AttendancePeriod'], SQLINT4),
            array('@AttendanceDayNumber', $data['AttendanceDayNumber'], SQLINT4),
            array('@StaffID', $data['StaffID'], SQLINT4),
            array('@AttendedFlag', 1, SQLINT4),
            array('@TimeTableGroup', $data['TimetableGroup'], SQLVARCHAR),
            array('@SynWebFlag', 1, SQLINT4),
            array('@wkAttendanceMasterSeq', $var, SQLINT4, true, true, -1),
        );


        $result = $this->getDatabase()->executeProcedure(
            'spiwkAttendances', $value

        );

        if ($result['success'] && isset($result['output']['@wkAttendanceMasterSeq'])) {
            $this->setAttendanceMasterSeq($result['output']['@wkAttendanceMasterSeq']);
        } else {
            throw new Exception_Schoolbox('Cannot setup attendance record, ' . $result['message']);
        }

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
        $result = $this->getDatabase()->executeProcedure(
            'sptwkAttendances', array(
                array('@wkAttendanceMasterSeq', $this->getAttendanceMasterSeq(), SQLINT4),
                array('@HoldFlag', 0, SQLINT1),
                array('@AttendanceMasterSeq', 'seq', SQLINT4, true),
            )

        );
        return $result['success'];
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
        if ($this->getAttendanceMasterSeq() > 0) {
            return true;
        }

        return false;
    }

    /* Getters/Setters
     */

    /**
     * Set attendance date
     *
     * @param string $attendanceDate
     */
    protected function setAttendanceDate($attendanceDate)
    {
        $this->attendanceDate = $attendanceDate;
    }

    /**
     * Get attendance date
     *
     * @return string
     */
    protected function getAttendanceDate()
    {
        return $this->attendanceDate;
    }

    /**
     * Set attendance period
     *
     * @param int $attendancePeriod
     */
    protected function setAttendancePeriod($attendancePeriod)
    {
        $this->attendancePeriod = $attendancePeriod;
    }

    /**
     * Get attendance period
     *
     * @return int
     */
    protected function getAttendancePeriod()
    {
        return $this->attendancePeriod;
    }

    /**
     * Set class code
     *
     * @param string $classCode
     */
    protected function setClassCode($classCode)
    {
        $this->classCode = $classCode;
    }

    /**
     * Get class code
     *
     * @return string
     */
    protected function getClassCode()
    {
        return $this->classCode;
    }

    /**
     * Set file semester
     *
     * @param int $fileSemester
     */
    protected function setFileSemester($fileSemester)
    {
        $this->fileSemester = $fileSemester;
    }

    /**
     * Get file semester
     *
     * @return int
     */
    protected function getFileSemester()
    {
        return $this->fileSemester;
    }

    /**
     * Set file year
     *
     * @param int $fileYear
     */
    protected function setFileYear($fileYear)
    {
        $this->fileYear = $fileYear;
    }

    /**
     * Get file year
     *
     * @return int
     */
    protected function getFileYear()
    {
        return $this->fileYear;
    }

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

    /**
     * Set data
     *
     * @param $data
     */
    protected function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Get data
     *
     * @return array
     */
    protected function getData()
    {
        return $this->data;
    }

    /**
     * Set class
     *
     * @param null $class
     */
    protected function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * Get class
     *
     * @return null
     */
    protected function getClass()
    {
        return $this->class;
    }

    /**
     * Set attendance master seq
     *
     * @param int $attendanceMasterSeq
     */
    protected function setAttendanceMasterSeq($attendanceMasterSeq)
    {
        $this->attendanceMasterSeq = $attendanceMasterSeq;
    }

    /**
     * Get attendance master seq
     *
     * @return int
     */
    public function getAttendanceMasterSeq()
    {
        return $this->attendanceMasterSeq;
    }

    /**
     * @param string $attendanceDay
     */
    public function setAttendanceDay($attendanceDay)
    {
        $this->attendanceDay = $attendanceDay;
    }

    /**
     * @param string $classCampus
     */
    public function setClassCampus($classCampus)
    {
        $this->classCampus = $classCampus;
    }

    /**
     * @return string
     */
    public function getClassCampus()
    {
        return $this->classCampus;
    }

}
