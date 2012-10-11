<?php
/**
 * Attendance_Synergetic_AttendanceMaster_Abstract
 *
 * Each instance of this represents a new row in the AttendanceMaster table.
 * Default values are defined in setup() and written to the db via insertStub()
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Attendance_Synergetic_AttendanceMaster_Abstract {

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
	 * The date in y-m-d of the class
	 *
	 * @var string
	 */
	protected $attendanceDate = '';

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
	 * @param $attendancePeriod
	 * @param $fileYear
	 * @param $fileSemester
	 * @param $database
	 */
	public function __construct($class, $attendanceDate, $attendancePeriod, $fileYear, $fileSemester, $database) {
		$this->setClass($class);
		$this->setClassCode($class['ClassCode']);
		$this->setAttendanceDate($attendanceDate->getDatabaseDate());
		$this->setAttendancePeriod($attendancePeriod);
		$this->setFileYear($fileYear);
		$this->setFileSemester($fileSemester);
		$this->setDatabase($database);

		$this->setup();
	}

	/**
	 * Hook method
	 */
	abstract protected function setup();

	/**
	 * Find the DayNumber value for a particular class, date and period
	 * and for a particular FileYear and FileSemester set of values.
	 *
	 * @return mixed
	 */
	protected function getTimetableDayNumber() {
		$query = "
			SELECT *
			FROM SynergyOne.dbo.Timetable
			WHERE FileType = 'A'
			AND FileYear = '{$this->getFileYear()}'
			AND FileSemester = '{$this->getFileSemester()}'
			AND PeriodNumber = '{$this->getAttendancePeriod()}'
			AND ClassCode = '{$this->getClassCode()}'
		";

		$result = $this->getDatabase()->getRow($query);

		if (is_array($result)) {
			return $result['DayNumber'];
		}

		return false;
	}

	/**
	 * Find the TimetableGroup value for a particular class, date and period
	 * and for a particular FileYear and FileSemester set of values.
	 *
	 * @return mixed
	 */
	protected function getTimetableGroup() {
		$query = "
			SELECT *
			FROM SynergyOne.dbo.Timetable
			WHERE FileType = 'A'
			AND FileYear = '{$this->getFileYear()}'
			AND FileSemester = '{$this->getFileSemester()}'
			AND PeriodNumber = '{$this->getAttendancePeriod()}'
			AND ClassCode = '{$this->getClassCode()}'
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
	public function insertStub() {
		$data = $this->getData();

		$query = "
			INSERT INTO SynergyOne.dbo.AttendanceMaster (
				CreatedDate, CreatedByID,
				ModifiedDate, ModifiedByID,
				FileType, FileYear, FileSemester,
				ClassCampus, ClassCode, StaffID,
				AttendanceDate, AttendancePeriod, AttendanceDateTimeFrom, AttendanceDateTimeTo, AttendanceDayNumber,
				TimetableGroup, ClassCancelledFlag, AttendanceOfficerModeFlag, SystemProcessNumber
			)
			VALUES (
				{$data['CreatedDate']}, '{$data['CreatedByID']}',
				{$data['ModifiedDate']}, '{$data['ModifiedByID']}',
				'{$data['FileType']}', '{$data['FileYear']}', '{$data['FileSemester']}',
				'{$data['ClassCampus']}', '{$data['ClassCode']}', '{$data['StaffID']}',
				'{$data['AttendanceDate']}', '{$data['AttendancePeriod']}', '{$data['AttendanceDateTimeFrom']}', '{$data['AttendanceDateTimeTo']}', '{$data['AttendanceDayNumber']}',
				'{$data['TimetableGroup']}', '{$data['ClassCancelledFlag']}', '{$data['AttendanceOfficerModeFlag']}', '{$data['SystemProcessNumber']}'
			)
			SELECT LAST_INSERT_ID=@@IDENTITY
		";

		/* Get the new inserted id
		 */
		$r = mssql_fetch_assoc($this->getDatabase()->query($query));

		$this->setAttendanceMasterSeq($r['LAST_INSERT_ID']);
	}

	/**
	 * Determine if there are attendance records for this particular
	 * class, date and period by checking if the AttendanceMasterSeq
	 * value was found
	 *
	 * @return bool
	 */
	public function hasAttendanceMasterSeq() {
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
	protected function setAttendanceDate($attendanceDate) {
		$this->attendanceDate = $attendanceDate;
	}

	/**
	 * Get attendance date
	 *
	 * @return string
	 */
	protected function getAttendanceDate() {
		return $this->attendanceDate;
	}

	/**
	 * Set attendance period
	 *
	 * @param int $attendancePeriod
	 */
	protected function setAttendancePeriod($attendancePeriod) {
		$this->attendancePeriod = $attendancePeriod;
	}

	/**
	 * Get attendance period
	 *
	 * @return int
	 */
	protected function getAttendancePeriod() {
		return $this->attendancePeriod;
	}

	/**
	 * Set class code
	 *
	 * @param string $classCode
	 */
	protected function setClassCode($classCode) {
		$this->classCode = $classCode;
	}

	/**
	 * Get class code
	 *
	 * @return string
	 */
	protected function getClassCode() {
		return $this->classCode;
	}

	/**
	 * Set file semester
	 *
	 * @param int $fileSemester
	 */
	protected function setFileSemester($fileSemester) {
		$this->fileSemester = $fileSemester;
	}

	/**
	 * Get file semester
	 *
	 * @return int
	 */
	protected function getFileSemester() {
		return $this->fileSemester;
	}

	/**
	 * Set file year
	 *
	 * @param int $fileYear
	 */
	protected function setFileYear($fileYear) {
		$this->fileYear = $fileYear;
	}

	/**
	 * Get file year
	 *
	 * @return int
	 */
	protected function getFileYear() {
		return $this->fileYear;
	}

	/**
	 * Set database
	 *
	 * @param \Database $database
	 */
	protected function setDatabase($database) {
		$this->database = $database;
	}

	/**
	 * Get database
	 *
	 * @return \Database
	 */
	protected function getDatabase() {
		return $this->database;
	}

	/**
	 * Set data
	 *
	 * @param $data
	 */
	protected function setData($data) {
		$this->data = $data;
	}

	/**
	 * Get data
	 *
	 * @return array
	 */
	protected function getData() {
		return $this->data;
	}

	/**
	 * Set class
	 *
	 * @param null $class
	 */
	protected function setClass($class) {
		$this->class = $class;
	}

	/**
	 * Get class
	 *
	 * @return null
	 */
	protected function getClass() {
		return $this->class;
	}

	/**
	 * Set attendance master seq
	 *
	 * @param int $attendanceMasterSeq
	 */
	protected function setAttendanceMasterSeq($attendanceMasterSeq) {
		$this->attendanceMasterSeq = $attendanceMasterSeq;
	}

	/**
	 * Get attendance master seq
	 *
	 * @return int
	 */
	public function getAttendanceMasterSeq() {
		return $this->attendanceMasterSeq;
	}
}
