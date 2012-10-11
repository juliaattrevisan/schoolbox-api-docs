<?php
/**
 * Attendance_Synergetic_AttendanceRecord
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Attendance_Synergetic_AttendanceRecord {

	/**
	 * An MSSQL database instance
	 *
	 * @var \Database
	 */
	protected $database = null;

	/**
	 * @param $database
	 */
	public function __construct($database) {
		$this->setDatabase($database);
	}

	/**
	 * Insert new attendance record
	 *
	 * @param $data
	 */
	public function create($data) {
		$query = "
			INSERT INTO SynergyOne.dbo.tAttendances (
				AttendanceMasterSeq, ID,
				PossibleAbsenceCode, PossibleDescription,
				AttendedFlag,
				ModifiedDate, ModifiedByID,
				PossibleReasonCode,
				UserFlag1, UserFlag2, UserFlag3, UserFlag4, UserFlag5,
				LateArrivalFlag, LateArrivalTime,
				EarlyDepartureFlag, EarlyDepartureTime,
				AbsenceEventsSeq,
				NonAttendCreatedAbsenceEventsFlag
			)
			VALUES (
				'{$data['AttendanceMasterSeq']}', '{$data['ID']}',
				'{$data['PossibleAbsenceCode']}', '{$data['PossibleDescription']}',
				'{$data['AttendedFlag']}',
				'{$data['ModifiedDate']}', '{$data['ModifiedByID']}',
				'{$data['PossibleReasonCode']}',
				'{$data['UserFlag1']}', '{$data['UserFlag2']}', '{$data['UserFlag3']}', '{$data['UserFlag4']}', '{$data['UserFlag5']}',
				'{$data['LateArrivalFlag']}', '{$data['LateArrivalTime']}',
				'{$data['EarlyDepartureFlag']}', '{$data['EarlyDepartureTime']}',
				'{$data['AbsenceEventsSeq']}',
				'{$data['NonAttendCreatedAbsenceEventsFlag']}'
			)
		";

		$this->getDatabase()->query($query);
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
}
