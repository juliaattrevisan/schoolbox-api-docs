<?php 
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	Function to get classlist of students from synergy. 	*
*   $Id: syn_classlist.php,v 1.6 2004/04/27 05:16:48 jleckie Exp $						    
*                                                           *
************************************************************/

function getStudentInfo($synID)
{
        //setup database
        $db = @mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
		if(!$db)
			error(65);
		mssql_select_db(MSSQL_db, $db);
 
		
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
			
	$result = mssql_query($query, $db);
	$userInfo =  mssql_fetch_array($result);
	return $userInfo;
}

function getCurrentSemester()
{
	$db = @mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
	if(!$db)
		error(65); 
	mssql_select_db(MSSQL_db, $db);

		
	#Get current FileSemester and FileYear
	$query = "SELECT FileSemester, FileYear
				FROM FileSemesters
				WHERE (SystemCurrentFlag=1)
	";
    
	$result = @mssql_query($query);
	return @mssql_fetch_array( $result);
}

function getSubjectList($synergyID, $group)
{
	$db = @mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
	if(!$db)
		return false; 
	mssql_select_db(MSSQL_db, $db);

	
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
				StudentClasses.ID=$synergyID";
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
	
	$result = mssql_query( $sql);
	if($result !== FALSE && $result !== TRUE)
	{
		if( mssql_num_rows( $result ) > 0 )
		{
			while($subject = @mssql_fetch_assoc($result))
			{
					$subjectList[] = array('code'=>$subject['ClassCode'], 'name'=>$subject['name']);
			}
			return $subjectList;
		}
	}
	return false;
	
}

function getEnrolledStudents($synergyID)
{
	$db = @mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
	if(!$db)
		error(65); 
	mssql_select_db(MSSQL_db, $db);

	
	$query = "SELECT Relationships.RelatedID
			FROM SynergyOne.dbo.Constituencies Constituencies, SynergyOne.dbo.Relationships Relationships
			WHERE Relationships.RelatedID = Constituencies.ID AND 
			Relationships.ID = $synergyID AND 
			Constituencies.ConstitCode='@SC'
			AND Relationships.Relationship IN ('SN','STSN','DR','STDR','WD')";

	$result = @mssql_query( $query);
	if($result !== FALSE && $result !== TRUE)
	{
		if( mssql_num_rows( $result ) > 0 )
		{
			while($student = @mssql_fetch_array($result))
			{
					$studentList[] = $student['RelatedID'];
			}
			return $studentList;
		}
	}
	return false;
}

?>
