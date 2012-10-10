<?php
/**
 * External_Synergetic_Attendance
 *
 * Note that Synergetic's table uses "Seq" where we use "id"
 * They are synonymous in meaning (though sometimes disorientating)
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class External_Synergetic_Attendance implements External_AttendanceInterface {

	/**
	 * The classCode whose attendances you are marking
	 *
	 * @var string
	 */
	private $classCode = '';

	/**
	 * The date in y-m-d of the class
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
	 * The primary key associated with the selected class, date and period
	 * This is used in SynergyOne.dbo.AttendanceMaster
	 *
	 * @var int
	 */
	private $attendanceMasterSeq = -1;

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
	 * Constructor
	 *
	 * @param $class
	 * @param Alaress_Date_Format $attendanceDate
	 * @param $attendancePeriod
	 * @param $database
	 */
	public function __construct($class, Alaress_Date_Format $attendanceDate, $attendancePeriod, $database) {
		$this->setClassCode($class['ClassCode']);
		$this->setAttendanceDate($attendanceDate->getDatabaseDate());
		$this->setAttendancePeriod($attendancePeriod);
		$this->setDatabase($database);

		/* Get the primary key associated with the selected class, date and period
 		 */
		$this->setAttendanceMasterSeq($this->findAttendanceMasterSeq($this->getClassCode(), $this->getAttendanceDate(), $this->getAttendancePeriod()));

		/* Hook method which should be protected if this class becomes subclassed
		 */
		$this->setup();
	}

	/**
	 * Hook method
	 */
	private function setup() {
	}

	/**
	 * Get the primary Attendance key associated with the selected class, date and period
	 *
	 * @param $classCode
	 * @param $attendanceDate
	 * @param $attendancePeriod
	 *
	 * @return int
	 */
	private function findAttendanceMasterSeq($classCode, $attendanceDate, $attendancePeriod) {
		$query = "
			SELECT AttendanceMasterSeq
			FROM SynergyOne.dbo.AttendanceMaster
			WHERE ClassCode='{$classCode}'
			AND AttendanceDate='{$attendanceDate} 00:00:00.000'
			AND AttendancePeriod='{$attendancePeriod}'
			ORDER BY AttendanceMasterSeq
		";

		$result = $this->getDatabase()->getRow($query);

		if (is_array($result)) {
			return $result['AttendanceMasterSeq'];
		}

		return -1;
	}

	/**
	 * Get a list of attendees for a particular class, date and period
	 *
	 * @return mixed
	 */
	public function findAttendanceRecords() {
		$query = "
			SELECT
				AttendanceSeq,
				ID as synergy_id,
				PossibleAbsenceCode,
				Description AS PossibleAbsenceDescription,
				PossibleReasonCode,
				PossibleDescription AS PossibleReasonDescription,
				AttendedFlag
			FROM SynergyOne.dbo.tAttendances
			LEFT JOIN SynergyOne.dbo.luAbsenceType ON SynergyOne.dbo.tAttendances.PossibleAbsenceCode = SynergyOne.dbo.luAbsenceType.Code
			WHERE AttendanceMasterSeq = '{$this->getAttendanceMasterSeq()}'
			ORDER BY AttendanceSeq
		";

		$result = $this->getDatabase()->getAll($query);

		if ($result) {
			$this->setAllAttendanceRecords($result);
		}

		return $this->getAllAttendanceRecords();
	}

	/**
	 * Find the attendance record for a particular synergy_id
	 * simply by traversing an array, rather than drumming the
	 * db all over again
	 *
	 * @param $externalId
	 *
	 * @return array
	 */
	public function getAttendanceRecord($externalId) {
		foreach ($this->getAllAttendanceRecords() as $currentAttendanceRecord) {
			if ($currentAttendanceRecord['synergy_id'] == $externalId) {
				return $currentAttendanceRecord;
			}
		}

		return false;
	}

	/**
	 * A list of options for the Absence dropdown
	 *
	 * @return mixed
	 */
	public function findAbsenceDropdownOptions() {
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
	public function findReasonDropdownOptions() {
		$query = "
			SELECT Code, Description
			FROM SynergyOne.dbo.luAbsenceReason
			WHERE ActiveFlag = 1
		";

		return $this->getDatabase()->getAll($query);
	}

	/**
	 * Find out of an attendance record exists for a particular person
	 * in the class, date and period specified
	 *
	 * @param $externalId
	 * @return bool
	 */
	public function existingRecord($externalId) {
		$query = "
			SELECT *
			FROM SynergyOne.dbo.tAttendances
			WHERE AttendanceMasterSeq = '{$this->getAttendanceMasterSeq()}'
			AND ID = '{$externalId}'
		";

		return $this->getDatabase()->getRow($query);
	}

	/**
	 * Create an attendance record
	 *
	 * @param $data
	 */
	public function create($data) {
		$query = "
			INSERT INTO
		";

		echo $query;
		exit(1);
	}

	/**
	 * Update an attendance record
	 *
	 * @param $data
	 *
	 * @return \PDOStatement
	 */
	public function update($data) {
		$query = "
			UPDATE SynergyOne.dbo.tAttendances
			SET
				PossibleAbsenceCode = '{$data['PossibleAbsenceCode']}',
				PossibleDescription = \"{$data['PossibleDescription']}\",
				PossibleReasonCode = '{$data['PossibleReasonCode']}',
				AttendedFlag = '{$data['AttendedFlag']}',
				ModifiedDate = GETDATE(),
				ModifiedByID = '{$data['ModifiedByID']}'
			WHERE AttendanceSeq = '{$data['AttendanceSeq']}'
			AND	ID = '{$data['ID']}'
		";

		return $this->getDatabase()->query($query);
	}

	/* Getters/Setters
	 */

	/**
	 * Set attendance date
	 *
	 * @param string $attendanceDate
	 */
	private function setAttendanceDate($attendanceDate) {
		$this->attendanceDate = $attendanceDate;
	}

	/**
	 * Get attendance date
	 *
	 * @return string
	 */
	private function getAttendanceDate() {
		return $this->attendanceDate;
	}

	/**
	 * Set attendance period
	 *
	 * @param int $attendancePeriod
	 */
	private function setAttendancePeriod($attendancePeriod) {
		$this->attendancePeriod = $attendancePeriod;
	}

	/**
	 * Get attendance period
	 *
	 * @return int
	 */
	private function getAttendancePeriod() {
		return $this->attendancePeriod;
	}

	/**
	 * Set class code
	 *
	 * @param string $classCode
	 */
	private function setClassCode($classCode) {
		$this->classCode = $classCode;
	}

	/**
	 * Get class code
	 *
	 * @return string
	 */
	private function getClassCode() {
		return $this->classCode;
	}

	/**
	 * Set database
	 *
	 * @param \Database $database
	 */
	private function setDatabase($database) {
		$this->database = $database;
	}

	/**
	 * Get database
	 *
	 * @return \Database
	 */
	private function getDatabase() {
		return $this->database;
	}

	/**
	 * Set attendance master seq
	 *
	 * @param int $attendanceMasterSeq
	 */
	private function setAttendanceMasterSeq($attendanceMasterSeq) {
		$this->attendanceMasterSeq = $attendanceMasterSeq;
	}

	/**
	 * Get attendance master seq
	 *
	 * @return int
	 */
	private function getAttendanceMasterSeq() {
		return $this->attendanceMasterSeq;
	}

	/**
	 * Set attendance records for a particular class, date and period
	 *
	 * @param array $allAttendanceRecords
	 */
	private function setAllAttendanceRecords($allAttendanceRecords) {
		$this->allAttendanceRecords = $allAttendanceRecords;
	}

	/**
	 * Get all attendance records for a particular class, date and period
	 *
	 * @return array
	 */
	private function getAllAttendanceRecords() {
		return $this->allAttendanceRecords;
	}
}
