<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2008 Lektec Consulting				*
*                                                           *
*	This file provides access to synergetic class info		*	    
*                                                           *
************************************************************/
require_once dirname(__FILE__) .'/mssql.php';


class SynergeticClass extends MSSQL{

	function SynergeticClass() {
		parent::MSSQL();
	}
	
	function getStudents($subCode) {

		if(stristr($subCode, ',')) {
				$classQuery = 'StudentClasses.ClassCode IN(';
				$subCodeArray = explode(',',$subCode);
				foreach($subCodeArray as $code) {
					$classQuery .= "'$code',";
				}
				$classQuery = substr($classQuery,0,-1) . ')';
		}
		else
			$classQuery = "StudentClasses.ClassCode = '$subCode'";

        $query = "
		SELECT DISTINCT  CommunityStudents.Surname as lastname,
			CommunityStudents.Preferred as firstname,
			StudentSemester.YearLevel as yearlevel,
			luHouse.Description as house,
			CommunityStudents.ID as synergy_id,
            vStudents.StudentMedicalAlert,
            vStudents.StudentSpecialNeedsFlag
		FROM    SynergyOne.dbo.Community CommunityStudents
		INNER JOIN SynergyOne.dbo.StudentSemester StudentSemester ON CommunityStudents.ID = StudentSemester.ID
		LEFT JOIN SynergyOne.dbo.StudentClasses StudentClasses ON (
			StudentSemester.FileYear = StudentClasses.FileYear AND
			StudentSemester.FileSemester = StudentClasses.FileSemester AND
			StudentSemester.ID = StudentClasses.ID)
        LEFT JOIN SynergyOne.dbo.vStudents vStudents ON (
			StudentSemester.FileYear = vStudents.FileYear AND
			StudentSemester.FileSemester = vStudents.FileSemester AND
			StudentSemester.ID = vStudents.ID)
		LEFT JOIN SynergyOne.dbo.SubjectClasses SubjectClasses ON (
			StudentClasses.FileType = SubjectClasses.FileType AND
			StudentClasses.FileYear = SubjectClasses.FileYear AND
			StudentClasses.FileSemester = SubjectClasses.FileSemester AND
			StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND
			StudentClasses.ClassCode = SubjectClasses.ClassCode )
		LEFT JOIN SynergyOne.dbo.luHouse luHouse ON (
			StudentSemester.House = luHouse.Code
			AND StudentSemester.StudentCampus = luHouse.Campus)
		LEFT JOIN SynergyOne.dbo.luYearLevel lY ON (
			StudentSemester.StudentCampus = lY.Campus
			AND StudentSemester.YearLevelSort = lY.YearLevelSort)
		LEFT JOIN SynergyOne.dbo.luStudentStatus lS ON (
			StudentSemester.Status = lS.Code)
		WHERE   CommunityStudents.DeceasedFlag = 0
			AND lS.SynergyMeaning <> 'LEFT'
			AND $classQuery
			AND (SubjectClasses.FileYear='" . $_SESSION['FileYear'] . "')
			AND (SubjectClasses.FileSemester='" . $_SESSION['FileSemester'] .  "')
			AND ( SubjectClasses.FileType != 'A' OR StudentClasses.StartDate <= getdate())
			AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)
		ORDER BY CommunityStudents.Surname, CommunityStudents.Preferred ";

		return $this->getAll($query);
	}
	
	function getTeacherName($code) {
			
			$msQuery = "SELECT DISTINCT StaffID, StaffSeq FROM SubjectClassStaff
				WHERE ClassCode = '$code' AND FileYear={$_SESSION['FileYear']} AND FileSemester={$_SESSION['FileSemester']}
				ORDER BY StaffSeq";
			$this->query($msQuery);
			while($synrow = $this->getNextArray() ) {
				$teachers[] = $synrow['StaffID'];
			}
			return $teachers;
	}
}

?>
