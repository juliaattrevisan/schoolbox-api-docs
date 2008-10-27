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
					luForm.ID as TeacherFormID, vStudents.StudentForm, luTutor.ID as TeacherTutorID, vStudents.StudentTutor, luForm.Description as FormDescription,
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
				AND vStudents.FileSemester=".$_SESSION['FileSemester']."
				AND vStudents.FileYear=".$_SESSION[ 'FileYear']."
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
			$sql ="SELECT StudentClasses.ClassCode, SubjectClasses.Description as name
					FROM StudentClasses, SubjectClasses
					WHERE 
					StudentClasses.FileType = SubjectClasses.FileType AND
					StudentClasses.FileYear = SubjectClasses.FileYear AND
					StudentClasses.FileSemester = SubjectClasses.FileSemester AND
					StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND
					StudentClasses.ClassCode = SubjectClasses.ClassCode AND
					StudentClasses.FileSemester=".$_SESSION['FileSemester']." AND
					StudentClasses.FileYear=".$_SESSION[ 'FileYear']." AND
					StudentClasses.ID=$synergyID
					AND StudentClasses.StartDate <= getdate() 
					AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)";
		}
		else
		{      
		
			$sql = "SELECT DISTINCT(c1.ClassCode), c2.description as name
					FROM SubjectClassStaff c1, SubjectClasses c2
					WHERE 
					c1.FileType = c2.FileType AND
					c1.FileYear = c2.FileYear AND
					c1.FileSemester = c2.FileSemester AND
					c1.ClassCampus = c2.ClassCampus AND
					c1.ClassCode = c2.ClassCode AND
					c1.FileSemester = ".$_SESSION['FileSemester']." AND
					c1.FileYear = ".$_SESSION[ 'FileYear']." AND
					c1.StaffID = $synergyID";
		}
		
		$this->query($sql);
		while($subject = $this->getNextArray() ) {
			print_r($subject);
			//remove study periods and rostered offs
			if(!preg_match("/ST|RO/",$subject['ClassCode']))
			{
				$subjectList[] = array('code'=>$subject['ClassCode'], 'name'=>$subject['name']);
			}
		}
		exit;
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