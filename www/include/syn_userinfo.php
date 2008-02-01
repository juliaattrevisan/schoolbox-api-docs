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
		SELECT 	vStudents.StudentID, vStudents.StudentGiven1, vStudents.StudentSurname, 
				vStudents.StudentTutor, vStudents.StudentYearLevel, vStudents.StudentBirthDate, 
				vStudents.StudentEmergencyName, vStudents.StudentEmergencyPhone, vStudents.StudentEmergencyPhoneAlt,
				contactDetails.StudentContactTitle, contactDetails.StudentContactPreferred, contactDetails.StudentContactSurname,
				contactDetails.StudentContactAddress1, contactDetails.StudentContactSuburb, contactDetails.StudentContactPostCode, contactDetails.StudentContactState,
				contactDetails.StudentContactPhoneActual, contactDetails.StudentContactMobilePhone, contactDetails.StudentContactOccupPhone,
				contactDetails.StudentContactSpouseTitle, contactDetails.StudentContactSpousePreferred, contactDetails.StudentContactSpouseSurname,
				contactDetails.StudentContactSpouseMobilePhone, contactDetails.StudentContactSpouseOccupPhone
		FROM 
				SynergyOne.dbo.vStudentContactAllAddress contactDetails, 
				SynergyOne.dbo.vStudents vStudents 
		WHERE 	vStudents.CurrentSemesterOnlyFlag = contactDetails.CurrentSemesterOnlyFlag 
			AND vStudents.FileSemester = contactDetails.FileSemester 
			AND vStudents.FileYear = contactDetails.FileYear 
			AND vStudents.StudentID = contactDetails.ID
			AND (contactDetails.StudentContactDescription='Contact 1' OR contactDetails.StudentContactDescription='Primary Contacts')
			AND vStudents.StudentID = $synID
		ORDER BY
			contactDetails.FileYear DESC,contactDetails.FileSemester DESC
			";
	$result = mssql_query($query, $db);
	return mssql_fetch_array($result);
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
	
	$result = @mssql_query( $sql);
	if($result !== FALSE && $result !== TRUE)
	{
		if( mssql_num_rows( $result ) > 0 )
		{
			while($subject = @mssql_fetch_array($result))
			{
					//remove study periods and rostered offs
        			if(!preg_match("/ST|RO/",$subject['ClassCode']))
        			{
						$subjectList[] = array('code'=>$subject['ClassCode'], 'name'=>$subject['name']);
					}
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
