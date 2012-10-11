<?php
/**
 * External_AttendanceInterface
 *
 * Interface for External Attendances, to mark who was absent/present for
 * a particular class
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
interface External_AttendanceInterface {
	public function findAttendanceRecords();
	public function getAttendanceRecord($externalId);
	public function findAbsenceDropdownOptions();
	public function findReasonDropdownOptions();
	public function existingRecord($externalId);
}
