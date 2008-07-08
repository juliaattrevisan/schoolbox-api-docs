<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2008 Lektec Consulting				*
*                                                           *
*	This file provides access to synergetic user info		*	    
*                                                           *
************************************************************/
require_once '../library/mssql.php';

class SynergeticUser extends MSSQL{

	function SynergeticUser() {
		parent::MSSQL();
	}
	
	function getStudent($synID)
	{
			$query = "
			SELECT	vStudents.StudentID, vStudents.StudentGiven1, vStudents.StudentSurname, vStudents.StudentBirthDate, 
					vStudents.StudentTutorName, vStudents.StudentHeadOfHouse, vStudents.StudentFormStaffName, vStudents.StudentTutorDescription,
					luForm.ID as TeacherFormID, vStudents.StudentForm, luTutor.ID as TeacherTutorID, vStudents.StudentTutor, 
					vStudents.StudentHouseDescription, vStudents.StudentHouse, vStudents.StudentYearLevel, vStudents.StudentLockerBook,
					vStudents.StudentEmergencyName, vStudents.StudentEmergencyPhone, vStudents.StudentEmergencyPhoneAlt, vStudents.StudentMedicalAlert,
					contactDetails.StudentContactTitle, contactDetails.StudentContactPreferred, contactDetails.StudentContactSurname, contactDetails.StudentContactDefaultEmail,
					contactDetails.StudentContactAddress1, contactDetails.StudentContactSuburb, contactDetails.StudentContactPostCode, contactDetails.StudentContactState,
					contactDetails.StudentContactPhoneActual, contactDetails.StudentContactMobilePhone, contactDetails.StudentContactOccupPhone,
					contactDetails.StudentContactSpouseTitle, contactDetails.StudentContactSpousePreferred, contactDetails.StudentContactSpouseSurname,
					contactDetails.StudentContactSpouseMobilePhone, contactDetails.StudentContactSpouseOccupPhone,contactDetails.StudentContactSpouseDefaultEmail
			FROM 
					SynergyOne.dbo.vStudentContactAllAddress contactDetails, 
					SynergyOne.dbo.vStudents vStudents,
					SynergyOne.dbo.luForm,
					SynergyOne.dbo.luTutor
			WHERE 	vStudents.CurrentSemesterOnlyFlag = contactDetails.CurrentSemesterOnlyFlag 
				AND vStudents.FileSemester = contactDetails.FileSemester 
				AND vStudents.FileYear = contactDetails.FileYear 
				AND vStudents.StudentID = contactDetails.ID
				AND vStudents.StudentForm=luForm.Code
				AND vStudents.StudentTutor=luTutor.Code
				AND (contactDetails.StudentContactDescription='Contact 1' OR contactDetails.StudentContactDescription='Primary Contacts')
				AND vStudents.StudentID = $synID
			ORDER BY
				contactDetails.FileYear DESC,contactDetails.FileSemester DESC
				"; 
		
		$student = $this->getRow($query);
		
		preg_match('/([\w]+)\s+([\d]+)\s+([\d]+)/', $student['StudentBirthDate'], $matches);
		$student['StudentBirthDate'] = strtotime($matches[2].' '.$matches[1].' '.$matches[3]);
		return $student;
		
	}

	function getCurrentSemester()
	{		
		#Get current FileSemester and FileYear
		$query = "SELECT FileSemester, FileYear FROM FileSemesters WHERE SystemCurrentFlag=1";
		return $this->getRow($query);
	}
	
	function getSubjectList($synergyID, $group)
	{	
		if( isStudent($group) )
		{
			$sql ="SELECT DISTINCT(SubjectClasses.ClassCode), SubjectClasses.Description as name
					FROM SubjectClasses SubjectClasses, vStudentTimetableFull vStudentTimetableFull
					WHERE SubjectClasses.ClassCode = vStudentTimetableFull.ClassCode AND
					SubjectClasses.FileSemester = vStudentTimetableFull.FileSemester AND
					SubjectClasses.FileType = vStudentTimetableFull.FileType AND
					SubjectClasses.FileYear = vStudentTimetableFull.FileYear AND
					((vStudentTimetableFull.FileSemester=".$_SESSION['FileSemester'].") AND
					(vStudentTimetableFull.FileYear=".$_SESSION[ 'FileYear'].") AND
					(vStudentTimetableFull.StudentID=$synergyID))";
		}
		else
		{      
			$sql = "SELECT DISTINCT(c1.ClassCode), c2.description as name
					FROM SubjectClassStaff c1, SubjectClasses c2
					WHERE c1.ClassCode = c2.ClassCode AND
					c1.FileSemester = ".$_SESSION['FileSemester']." AND
					c1.FileYear = ".$_SESSION[ 'FileYear']." AND
					c1.StaffID = $synergyID AND
					c2.Description <= all ( select description from subjectclasses c3 where c3.classcode=c1.classcode)";
		}
		
		$this->query($sql);
		while($subject = $this->getNextArray() ) {
			//remove study periods and rostered offs
			if(!preg_match("/ST|RO/",$subject['ClassCode']))
			{
				$subjectList[] = array('code'=>$subject['ClassCode'], 'name'=>$subject['name']);
			}
		}
		if(is_array($subjectList))
			return $subjectList;
		else 
			return false;
	}
	
	function getEnrolledStudents($synergyID)
	{	
		$query = "SELECT Relationships.RelatedID
				FROM SynergyOne.dbo.Constituencies Constituencies, SynergyOne.dbo.Relationships Relationships
				WHERE Relationships.RelatedID = Constituencies.ID AND 
				Relationships.ID = $synergyID AND 
				Constituencies.ConstitCode='@SC'
				AND Relationships.Relationship IN ('SN','STSN','DR','STDR','WD')";
	
		
		return $this->getAll($query);
		
	}

}


?>