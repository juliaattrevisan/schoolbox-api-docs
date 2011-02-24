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
		SELECT StudentSurname as lastname, StudentPreferred as firstname,
                        StudentYearLevelDescription as yearlevel, StudentHouseDescription as house,
                        StudentMedicalAlert, StudentSpecialNeedsFlag,
                        StudentID as synergy_id
		FROM    SynergyOne.dbo.StudentClasses StudentClasses
		JOIN SynergyOne.dbo.vStudents vStudents ON (
			vStudents.FileYear = StudentClasses.FileYear AND
			vStudents.FileSemester = StudentClasses.FileSemester AND
			vStudents.ID = StudentClasses.ID)
		WHERE  StudentActiveFlag = 1 AND StudentStatus = 'NOR'
			AND $classQuery
			AND (StudentClasses.FileYear='" . $_SESSION['FileYear'] . "')
			AND (StudentClasses.FileSemester='" . $_SESSION['FileSemester'] .  "')
			AND ( StudentClasses.FileType != 'A' OR StudentClasses.StartDate <= getdate())
			AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)
		ORDER BY StudentSurname, StudentPreferred";

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
