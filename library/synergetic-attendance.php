<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2008 Lektec Consulting				*
*                                                           *
*	This file provides access to synergetic attendance		*	    
*                                                           *
************************************************************/
require_once '../library/mssql.php';

class SynergeticAttendance extends MSSQL{

	function SynergeticAttendance() {
		parent::MSSQL();
	}

	function classList($code, $date, $period) {
	$query = "
			SELECT CommunityStudents.Surname,
				CommunityStudents.Preferred,
				Students.ID, 
				Attendances.AttendedFlag, 
				Attendances.PossibleAbsenceCode, 
				Attendances.PossibleDescription
			FROM	SynergyOne.dbo.Students Students
			INNER JOIN SynergyOne.dbo.StudentSemester StudentSemester ON Students.ID = StudentSemester.ID
			LEFT JOIN SynergyOne.dbo.StudentClasses StudentClasses ON (
				StudentSemester.FileYear = StudentClasses.FileYear AND
				StudentSemester.FileSemester = StudentClasses.FileSemester AND
				StudentSemester.ID = StudentClasses.ID)
			LEFT JOIN SynergyOne.dbo.SubjectClasses SubjectClasses ON (
				StudentClasses.FileType = SubjectClasses.FileType AND
				StudentClasses.FileYear = SubjectClasses.FileYear AND
				StudentClasses.FileSemester = SubjectClasses.FileSemester AND
				StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND
				StudentClasses.ClassCode = SubjectClasses.ClassCode )
			LEFT JOIN SynergyOne.dbo.Community CommunityStudents ON 
				Students.ID = CommunityStudents.ID
			LEFT JOIN SynergyOne.dbo.luStudentStatus lS ON (
				StudentSemester.Status = lS.Code)
			LEFT JOIN SynergyOne.dbo.Attendances Attendances ON ( 
				Students.ID = Attendances.ID AND
				Attendances.ClassCode = StudentClasses.ClassCode
				AND Attendances.AttendancePeriod = '" . $period . "'
				AND Attendances.AttendanceDate = '" . $date . "')	
			WHERE	CommunityStudents.DeceasedFlag = 0
				AND lS.SynergyMeaning <> 'LEFT'  
				AND lS.SynergyMeaning <> 'LOA'  
				AND StudentClasses.ClassCode = '" . $code . "'
				AND (SubjectClasses.FileYear='" . $_SESSION['FileYear']  . "')
				AND (SubjectClasses.FileSemester='" . $_SESSION['FileSemester'] .  "')
		ORDER BY CommunityStudents.Surname, CommunityStudents.Preferred ";

		return $this->db->getAll($query);
	
	}

	function startAttendanceInsert($code, $period,$day)
	{
		//initalise some variables to make insert faster
		$this->code = $code;
		$this->period = $period;
		$this->day = $day;
	}

	function insertAttendance($attendSeq, $stuid, $attend, $abstype, $reason)
	{
		$query = "INSERT INTO SynergyOne.dbo.tAttendances
			( ID, AttendanceMasterSeq,AttendedFlag,
			PossibleAbsenceCode,PossibleDescription)
			VALUES
			('".$stuid."', 
			'".$attendSeq."',
			'".$attend."', 
			'".$abstype."', 
			'".$reason."')";

		$this->db->query($query);
	}

	function updateAttendance($attendSeq, $stuid, $attend, $abstype, $reason)
	{

		$query = "UPDATE SynergyOne.dbo.tAttendances 
			  SET AttendedFlag = '$attend', 
			      PossibleAbsenceCode = '$abstype', 
			      PossibleDescription = '$reason'
			  WHERE AttendanceMasterSeq = '$attendSeq' AND
				ID = '$stuid'";
		$this->db->query($query);
	}

	function checkAttendance()
	{
		//checks if attendance has been recorded

		$query = "SELECT AttendanceMasterSeq FROM SynergyOne.dbo.AttendanceMaster
			  WHERE FileType = 'A' AND
				FileYear = '".$_SESSION['FileYear']."' AND
				FileSemester = '".$_SESSION['FileSemester']."' AND
				ClassCampus = '".$this->campus."' AND
				ClassCode = '".$this->code."' AND
				AttendanceDate = '".$this->date."' AND
				AttendancePeriod = '".$this->period."'";
		
		if($attendanceSeq = $this->db->getOne($query) )
			return $attendanceSeq;
		else
			return false;
	}

	function initAttendance() {
		//inserts attendance record into master table
		$query = "INSERT INTO SynergyOne.dbo.AttendanceMaster
			(FileType, FileYear, FileSemester, ClassCampus, ClassCode,
                        AttendanceDate, AttendanceDayNumber, AttendancePeriod, StaffID)
                        VALUES
                        ('A',
                        '".$_SESSION['FileYear']."',
                        '".$_SESSION['FileSemester']."',
                        '".$this->campus."',
                        '".$this->code."',
                        '".$this->date."',
			'".$this->day."',
                        '".$this->period."',
                        '".$_SESSION['sid']."')";
		
		return $this->db->query($query);
	}
	
}

?>
