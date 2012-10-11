<?php
/**
 * External_Synergetic_Attendance
 *
 * Note that Synergetic's table uses "Seq" where we use "id"
 * They are synonymous in meaning (though sometimes disorientating)
 *
 * Reset sql for admin:

DELETE FROM SynergyOne.dbo.tAttendances WHERE AttendanceMasterSeq > 336;
DELETE FROM SynergyOne.dbo.AttendanceMaster WHERE AttendanceMasterSeq > 336;

SELECT * FROM SynergyOne.dbo.AttendanceMaster ORDER BY AttendanceMasterSeq DESC;
SELECT * FROM SynergyOne.dbo.tAttendances ORDER BY AttendanceMasterSeq DESC;

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
	public function __construct($class, Alaress_Date_Format $attendanceDate, $attendancePeriod, $fileYear, $fileSemester, $database) {
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
		$attendanceMaster = new Attendance_Synergetic_AttendanceMaster_Existing($class, $attendanceDate, $attendancePeriod, $fileYear, $fileSemester, $database);

		if (!$attendanceMaster->hasAttendanceMasterSeq()) {
			$attendanceMaster = new Attendance_Synergetic_AttendanceMaster_New($class, $attendanceDate, $attendancePeriod, $fileYear, $fileSemester, $database);
		}

		$this->setAttendanceMaster($attendanceMaster);

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
	 * Get all the student ids for a particular AttendanceMasterSeq
	 *
	 * @param $attendanceMasterSeq
	 * @return mixed
	 */
	private function getStudentIds($attendanceMasterSeq) {
		$query = "
			SELECT ID
			FROM SynergyOne.dbo.tAttendances
			WHERE attendanceMasterSeq = '{$attendanceMasterSeq}'
		";

		$allIds = array();

		$result = $this->getDatabase()->getAll($query);

		if (is_array($result) && count($result)) {
			foreach($result as $current) {
				$allIds[] = $current['ID'];
			}
		}

		return $allIds;
	}

	/**
	 * Get student data given a set of ids associated with a particular AttendanceMasterSeq
	 * and thus associated with a particular point in time
	 *
	 * @param $attendanceMasterSeq
	 *
	 * @internal param $ids
	 * @return mixed
	 */
	public function getStudents($attendanceMasterSeq) {
		$allIds = $this->getStudentIds($attendanceMasterSeq);

		if (count($allIds)) {
			$ids = implode(',', $allIds);

			$query = "
				SELECT DISTINCT
					StudentSurname as lastname, StudentPreferred as firstname,
					StudentYearLevelDescription as yearlevel, StudentHouseDescription as house,
					StudentMedicalAlert, StudentSpecialNeedsFlag,
					StudentID as synergy_id
				FROM
					SynergyOne.dbo.StudentClasses StudentClasses
				JOIN SynergyOne.dbo.vStudents vStudents ON (
					vStudents.FileYear = StudentClasses.FileYear AND
					vStudents.FileSemester = StudentClasses.FileSemester AND
					vStudents.ID = StudentClasses.ID
				)
				WHERE StudentID IN ({$ids})
				AND (StudentClasses.FileYear='{$this->getFileYear()}')
				AND (StudentClasses.FileSemester='{$this->getFileSemester()}')
				AND (StudentClasses.FileType != 'A' OR StudentClasses.StartDate <= getdate())
				AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)
				ORDER BY StudentSurname, StudentPreferred
			";

			return $this->getDatabase()->getAll($query);
		}

		return array();
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
	 * Determine if there are attendance records for this particular
	 * class, date and period by checking if the AttendanceMasterSeq
	 * value was found
	 *
	 * @return bool
	 */
	public function hasAttendanceMasterSeq() {
		return $this->getAttendanceMaster()->hasAttendanceMasterSeq();
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
	 * Delegation method
	 *
	 * Get attendance master seq
	 *
	 * @return int
	 */
	private function getAttendanceMasterSeq() {
		return $this->getAttendanceMaster()->getAttendanceMasterSeq();
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

	/**
	 * Set attendance master
	 *
	 * @param null $attendanceMaster
	 */
	private function setAttendanceMaster($attendanceMaster) {
		$this->attendanceMaster = $attendanceMaster;
	}

	/**
	 * Get attendance master
	 *
	 * @return null
	 */
	public function getAttendanceMaster() {
		return $this->attendanceMaster;
	}

	/**
	 * Set file year
	 *
	 * @param int $fileYear
	 */
	private function setFileYear($fileYear) {
		$this->fileYear = $fileYear;
	}

	/**
	 * Get file year
	 *
	 * @return int
	 */
	private function getFileYear() {
		return $this->fileYear;
	}

	/**
	 * Set file semester
	 *
	 * @param int $fileSemester
	 */
	private function setFileSemester($fileSemester) {
		$this->fileSemester = $fileSemester;
	}

	/**
	 * Get file semester
	 *
	 * @return int
	 */
	private function getFileSemester() {
		return $this->fileSemester;
	}
}
