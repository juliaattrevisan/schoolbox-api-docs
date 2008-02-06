<?php 
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	Function to get classlist of students from synergy. 	*
*   $Id: syn_classlist.php,v 1.6 2004/04/27 05:16:48 jleckie Exp $						    
*                                                           *
************************************************************/

function getStudents($subCode, $db = 0)
{
        //setup database
        $db = mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
		if(!$db)
			error(65);
		mssql_select_db(MSSQL_db, $db);

        $query = "
		SELECT  CommunityStudents.Surname as lastname,
			CommunityStudents.Preferred as firstname,
			StudentSemester.YearLevel as yearlevel,
			luHouse.Description as house,
			Students.ID as synergy_id
		FROM    SynergyOne.dbo.Students Students
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
			AND StudentClasses.ClassCode = '" . $subCode . "'
			AND (SubjectClasses.FileYear='" . $_SESSION['FileYear'] . "')
			AND (SubjectClasses.FileSemester='" . $_SESSION['FileSemester'] .  "')
		ORDER BY CommunityStudents.Surname, CommunityStudents.Preferred ";

	$result = mssql_query($query, $db);
	return $result;
}

function getTeacherName($code) {
		
		$db = @mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);
		@mssql_select_db(MSSQL_db, $db);
	
		$msQuery = "SELECT DISTINCT StaffID FROM SubjectClassStaff
			WHERE ClassCode = '$code' AND FileYear={$_SESSION['FileYear']} AND FileSemester={$_SESSION['FileSemester']}";
		
		$msResult = @mssql_query($msQuery, $db);
		
		if(@mssql_num_rows($msResult) > 0) {

			while($synrow = @mssql_fetch_array($msResult))
			{
				$teachers[] = $synrow['StaffID'];	
			}
			return $teachers;
		}
}


?>
