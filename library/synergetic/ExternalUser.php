<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2008 Lektec Consulting				*
*                                                           *
*	This file provides access to synergetic user info		*	    
*                                                           *
************************************************************/
require_once dirname(__FILE__) .'/../mssql.php';
require_once dirname(__FILE__) .'/../group.php';

class ExternalUser extends MSSQL{

    function __construct() {
            parent::MSSQL();
            $this->groupHandler = new Group;
    }

    function getParent($synID) {

        $query = "SELECT    StudentContactLegalFullName, StudentContactEmail, StudentContactMobilePhone,
                            StudentContactSpouseLegalFullName, StudentContactSpouseMobilePhone, StudentContactSpouseEmail,
                            StudentContactHomePhone, StudentContactSilentPhoneFlag, Contact.DirectoryIncludeFlag PrimaryDirectoryIncludeFlag, Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag,
                            StudentParentsSeparatedFlag
        		  FROM
				  	SynergyOne.dbo.vStudentContactAllAddress
                    JOIN SynergyOne.dbo.Community Contact ON StudentContactID = Contact.ID
                    JOIN SynergyOne.dbo.Community Spouse ON StudentContactSpouseID = Spouse.ID
				  WHERE
				  	StudentContactID = $synID
					AND FileSemester=".$_SESSION['FileSemester']."
					AND FileYear=".$_SESSION[ 'FileYear']."
                    ";
        return $this->getRow($query);
        

    }

	function getStudent($synID)
	{
			$query = "
			SELECT	vStudents.StudentID, vStudents.StudentGiven1, vStudents.StudentSurname, vStudents.StudentBirthDate, 
					vStudents.StudentTutorName, vStudents.StudentHeadOfHouse, vStudents.StudentFormStaffName, vStudents.StudentTutorDescription,
					luForm.ID as TeacherFormID, vStudents.StudentForm, luTutor.ID as TeacherTutorID, vStudents.StudentTutor, luForm.Description as FormDescription,
					vStudents.StudentHouseDescription, vStudents.StudentHouse, vStudents.StudentYearLevel, vStudents.StudentLockerBook,
					vStudents.StudentEmergencyName, vStudents.StudentEmergencyPhone, vStudents.StudentEmergencyPhoneAlt, vStudents.StudentMedicalAlert,vStudents.StudentSpecialNeedsFlag, AlertMessage
                    FROM SynergyOne.dbo.vStudents vStudents
                    LEFT JOIN CommunityAlerts ON CommunityAlerts.ID = StudentID
					LEFT JOIN SynergyOne.dbo.luForm ON vStudents.StudentForm=luForm.Code
					LEFT JOIN SynergyOne.dbo.luTutor ON vStudents.StudentTutor=luTutor.Code
			WHERE 	
				vStudents.StudentID = $synID
				AND vStudents.FileSemester=".$_SESSION['FileSemester']."
				AND vStudents.FileYear=".$_SESSION[ 'FileYear']."
				"; 
				
		$student = $this->getRow($query);

		preg_match('/([\w]+)\s+([\d]+)\s+([\d]+)/', $student['StudentBirthDate'], $matches);
		$student['StudentBirthDate'] = strtotime($matches[2].' '.$matches[1].' '.$matches[3]);
		return $student;
		
	}

       function getStaff($synID)
	{
            $query = " SELECT SchoolStaffCode, StaffBuilding, StaffRoom, StaffExtension, StaffMobilePhone
                FROM SynergyOne.dbo.vStaff vStaff
                WHERE vStaff.StaffID = $synID ";

            $staff = $this->getRow($query);
            return $staff;

	}

	function getStudentContactList ($synID) {
		$query = "SELECT StudentContactID, StudentContactTitle, StudentContactPreferred, StudentContactSurname, StudentContactDefaultEmail,
                                StudentContactAddress1, StudentContactSuburb, StudentContactPostCode, StudentContactState,
                                StudentContactPhoneActual, StudentContactMobilePhone, StudentContactOccupPhone,
                                StudentContactSpouseID, StudentContactSpouseTitle, StudentContactSpousePreferred, StudentContactSpouseSurname,
                                StudentContactSpouseMobilePhone, StudentContactSpouseOccupPhone,StudentContactSpouseDefaultEmail, StudentContactType
                          FROM
                                SynergyOne.dbo.vStudentContactAllAddress

                          WHERE
                                (StudentContactType = 'SC1' OR StudentContactType = 'SC2') AND StudentID = $synID
                                AND FileSemester=".$_SESSION['FileSemester']."
                                AND FileYear=".$_SESSION[ 'FileYear']."
                          ";

		$parent = $this->getAll($query);
                return $parent;
	}

	function getCurrentSemester()
	{		
		#Get current FileSemester and FileYear
		$query = "SELECT FileSemester, FileYear FROM FileSemesters WHERE SystemCurrentFlag=1";
                $semester = $this->getRow($query);
                $_SESSION['FileSemester'] = $semester['FileSemester'];
                $_SESSION['FileYear'] = $semester['FileYear'];
		return true;
	}
	
	function getSubjectList($synergyID, $userID)
	{	
		if( $this->groupHandler->isStudent($userID) )
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
			$subjectList[] = array('code'=>$subject['ClassCode'], 'name'=>$subject['name']);
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

    function getEmail($id) {
        $query = "SELECT Email FROM Community WHERE id = $id";
        return $this->getOne($query);
    }



}


?>