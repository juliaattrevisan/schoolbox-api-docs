<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//absentee system, interfaces with synergetic database to

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/permission.php");
require_once("../include/syn_timetable.php");


//check if they are a staff member, students cannot access this
if(isStudent($_SESSION['gid']))
{
      error(19);
}

class Attendance
{

	function Attendance()
	{
		$this->year = date("Y");
		$this->date = date("Y-m-d");

		$this->msdb = mssql_connect(Cfg::get('external_db_host'), Cfg::get('external_db_user'), Cfg::get('external_db_passwd'));
	}

        function displayStudentList($subCode, $daynum)
        {
                $this->t_body = new Template(TPL_directory);
                $this->t_body->set_file("tplBODY", "absentees/studentList.htm");
		$this->t_body->set_block("tplBODY", "student_list", "student_list_block");
		$this->t_body->set_var("TITLE", HEADING." - Attendances Roll");
		include_once("../include/page.php");
		

		if (!$daynum)
			$daynum = getDayNumber($_SESSION['campus'][0]);
		$periodnum = $this->getSubjectPeriod($subCode, $daynum);
		if ($periodnum === false)
			error(33); //period doesn't exist today

		$this->t_body->set_var("DAYNUMBER", $daynum);
		$this->t_body->set_var("PERNUMBER", $periodnum);
		$this->t_body->set_var("SUBCODE", $subCode);
		$this->t_body->set_var("DATE",  date("j/n/Y"));
	 	$this->t_body->set_var("PERIOD", "");	

                //setup database
		//$mySQLdb = new dbManager();
		//$mySQLdb->dbConnect();	

		$query = "SELECT SubjectClasses.Description 
                       	FROM SynergyOne.dbo.SubjectClasses SubjectClasses
                       	WHERE SubjectClasses.ClassCode = '$subCode'";

		$result = mssql_query($query, $this->msdb);
		$name = mssql_fetch_array($result);
		$this->t_body->set_var("SUBJECTNAME", $name[0]);	

		$query = "
			SELECT CommunityStudents.Surname,
				CommunityStudents.Preferred,
				Students.ID, 
				Attendances.AttendedFlag, 
				Attendances.PossibleAbsenceCode, 
				Attendances.PossibleDescription,
				SubjectClasses.ClassCampus
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
				AND Attendances.AttendancePeriod = '" . $periodnum . "'
				AND Attendances.AttendanceDate = '" . $this->date . "')	
			WHERE	CommunityStudents.DeceasedFlag = 0
				AND lS.SynergyMeaning <> 'LEFT'  
				AND lS.SynergyMeaning <> 'LOA'  
				AND StudentClasses.ClassCode = '" . $subCode . "'
				AND (SubjectClasses.FileYear='" . $_SESSION['FileYear']  . "')
				AND (SubjectClasses.FileSemester='" . $_SESSION['FileSemester'] .  "')
				AND StudentClasses.StartDate <= getdate() AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)
		ORDER BY CommunityStudents.Surname, CommunityStudents.Preferred ";

		$result = mssql_query($query, $this->msdb);
		
		$x=0;		

		while($student = mssql_fetch_array($result))
 		{
			$this->t_body->set_var("SURNAME", $student[0]);
			$this->t_body->set_var("FIRSTNAME", $student[1]);
			$this->t_body->set_var("SYNERGYID", $student[2]);

			//now the absentee stuff
			$abscode = $this->createAbsCodeHTML($student['PossibleAbsenceCode']);

			$this->t_body->set_var("ABSCODENAME", "abscode".$x);
			$this->t_body->set_var("ABSCODE", $abscode);
			$this->t_body->set_var("ATTENDNAME", "attend".$x);
			
			if($student['AttendedFlag'] == 1 || is_null($student['AttendedFlag']))
			{
				$this->t_body->set_var("ATTENDED", "checked");
			}
			else
			{
				$this->t_body->set_var("ATTENDED", "");
			}

			$this->t_body->set_var("REASONNAME", "reason".$x);
			$this->t_body->set_var("REASON", $student['PossibleDescription']);
			$this->t_body->set_var("STUDNAME", "stud".$x);
			$this->t_body->set_var("STUDCODE", $student['ID']);
			if($student['ClassCampus'])
				$classCampus = $student['ClassCampus'];
				
			$this->t_body->parse("student_list_block", "student_list", true);		
			$x++;
		}		
         
		 if($x==0)
			$this->t_body->set_var("student_list_block", "");

			$this->t_body->set_var('CLASS_CAMPUS', $classCampus);

		$this->t_body->pparse("finaloutput", "tplBODY");
        }

	function createAbsCodeHTML($code)
	{
		$abscodeHTML = "";
	
		$query = "SELECT * FROM SynergyOne.dbo.luAbsenceType";
		$result = mssql_query($query, $this->msdb);		

		while( $desc = mssql_fetch_array($result) )
		{
			if($desc[0] == $code)
				$abscodeHTML .= "<option value=\"".$desc[0]."\" selected>".trim($desc[1])."</option>";
			else
				$abscodeHTML .= "<option value=\"".$desc[0]."\">".trim($desc[1])."</option>";
		}

		return $abscodeHTML;
	}

	function displayClassList($daynum)
	{
                $this->t_body = new Template(TPL_directory);
                $this->t_body->set_file("tplBODY", "absentees/classList.htm");
		$this->t_body->set_block("tplBODY", "class_list", "class_list_block");
		$this->t_body->set_var("TITLE", HEADING." - Attendances Class List");
		include_once("../include/page.php");

                //setup database
		$this->msdb = mssql_connect(Cfg::get('external_db_host'),Cfg::get('external_db_user'),Cfg::get('external_db_passwd'));

		$synergy_id = $_SESSION['sid'];

		//need method for detecting what day it is
		if(!$daynum)
			$daynum = getDayNumber($_SESSION['campus'][0]);

		$this->t_body->set_var("DAYNO", "Day&nbsp;".$daynum);
		$this->t_body->set_var("DATE", date("j/n/Y"));
	
		//get teacher name 
		$mydb = new DeprecatedDatabase;
		$mydb->dbConnect();
		$query = "SELECT firstname, lastname FROM user WHERE id = '".$_SESSION['id']."'";
		$mydb->dbDoQuery($query);
		$teacher = $mydb->dbGetNextArray();
		
		$this->t_body->set_var("TEACHERNAME", $teacher['firstname']." ".$teacher['lastname']);

            
                $query = "SELECT Timetable.Room, SubjectClasses.Description,
                       	  Timetable.PeriodNumber, Timetable.ClassCode, SubjectClasses.ClassCampus
                          FROM SynergyOne.dbo.SubjectClasses SubjectClasses, SynergyOne.dbo.Timetable Timetable
                          WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND
                          Timetable.ClassCode = SubjectClasses.ClassCode AND Timetable.FileSemester =
                          SubjectClasses.FileSemester AND Timetable.FileType = SubjectClasses.FileType
                          AND Timetable.FileYear = SubjectClasses.FileYear AND
                          ((SubjectClasses.FileSemester=".$_SESSION['FileSemester'].") AND (SubjectClasses.FileYear='".$_SESSION['FileYear']."')
                          AND (Timetable.StaffID='$synergy_id')) AND Timetable.DayNumber = '$daynum'
			  ORDER BY Timetable.PeriodNumber
		";

                $result = mssql_query($query, $this->msdb);
		$x = 0;
                while( $desc = mssql_fetch_array($result) )
                {
                        //output list of classes
                        $this->t_body->set_var("ROOM", $desc[0]);
                        $this->t_body->set_var("DESC", $desc[1]);
                        $this->t_body->set_var("PERNO", $desc[2]);
                        $this->t_body->set_var("CODE", $desc[3]);
                        $this->t_body->set_var("CAMPUS", $desc[4]);
                        $this->t_body->set_var("SUBJECTLINK", "attendances.php?code=".$desc[3]);
      
                        $this->t_body->parse("class_list_block", "class_list", true);
			$x++;
                }
		if($x==0)
			$this->t_body->set_var("class_list_block", "");

                $this->t_body->pparse("finaloutput", "tplBODY");
	}

	function getSubjectPeriod($code, $day)
	{	
		//give a subject code and day this function returns the period that it occurs in
		//or it returns 0 on failure

                //setup database

		$query = "SELECT Timetable.PeriodNumber
			FROM SynergyOne.dbo.Timetable Timetable
			WHERE Timetable.FileSemester='".$_SESSION['FileSemester']."' AND Timetable.FileYear='".$this->year."' 
			AND Timetable.DayNumber = '$day' AND Timetable.ClassCode = '$code'
		";

		$result = mssql_query($query, $this->msdb);
		if ($result != 1){
			if(mssql_num_rows($result) > 0)
			{
				$array = mssql_fetch_array($result);
				return $array[0];
			}	
			else	
				return false;
		}
		else 
			return false;

	}

	function involvedInSubjectSyn($code)
	{	
		//returns 1 when user is invovled in subject according to synergy
                //setup database

		$query = "SELECT Timetable.PeriodNumber
			FROM SynergyOne.dbo.Timetable Timetable
			WHERE Timetable.FileSemester='".$_SESSION['FileSemester']."' AND Timetable.FileYear='".$_SESSION['FileYear']."' 
			AND Timetable.StaffID = '".$_SESSION['sid']."' AND Timetable.ClassCode = '$code'";

		$result = mssql_query($query, $this->msdb);
	
		if ($result != 1){
		if(mssql_num_rows($result) > 0)
			return true;
		}
		else 
			return false;
	}


	function startAttendanceInsert($code, $period,$day, $campus)
	{
		//initalise some variables to make insert faster
		$this->code = $code;
		$this->period = $period;
		$this->day = $day;
		$this->campus = $campus;
	}

	function insertAttendance($batchCode, $stuid, $attend, $abstype, $reason)
	{
		$query = "INSERT INTO SynergyOne.dbo.xiattendances
			( BatchNumber, ID, AttendedFlag,
			PossibleAbsenceCode,PossibleDescription)
			VALUES
			('".$batchCode."',
			'".$stuid."', 
			'".$attend."', 
			'".$abstype."', 
			'".$reason."')";

		return $query;
	}

	function updateAttendance($attendSeq, $stuid, $attend, $abstype, $reason)
	{

		$query = "UPDATE SynergyOne.dbo.tAttendances 
			  SET AttendedFlag = '$attend', 
			      PossibleAbsenceCode = '$abstype', 
			      PossibleDescription = '$reason'
			  WHERE AttendanceMasterSeq = '$attendSeq' AND
				ID = '$stuid'";
		return $query;
	}

	function runQuery($query)
	{
		mssql_query($query, $this->msdb);
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
		
		$result = mssql_query($query, $this->msdb);


		if ($result != 1) {	
			if(mssql_num_rows($result) > 0) {
				$array = mssql_fetch_array($result);		
				return $array[0];
			}
			else
				return 0;
		}
		else 
			return 0;	
	}
	
	function runProc($batchCode, $attendSeq) {
		$query = "DECLARE @AttendanceMasterSeq INT ";
		
		if($attendSeq) {
			$query .= "SET @AttendanceMasterSeq = @AttendanceMasterSeqAlreadySubmitted";
		}
		else{
			$query .= "SET @AttendanceMasterSeq = NULL";
		}
		
		$query .= "
		EXEC SynergyOne.dbo.sptxiAttendances
				  @BatchNumber = '$batchCode',
				  @FileType = 'A', 			
				  @FileYear = '".$_SESSION['FileYear']."',
				  @FileSemester = '".$_SESSION['FileSemester']."',
				  @ClassCampus = '".$this->campus."',
				  @ClassCode = '".$this->code."',
				  @StaffID = '".$_SESSION['sid']."',
				  @AttendanceDate = '".$this->date."',
				  @AttendancePeriod = '".$this->day."',  
				  @AttendanceDayNumber =  '".$this->period."',
				  @TimetableGroup = 'ALL', 
				";
		if($attendSeq)
			$query .= "@AttendanceMasterSeq = '".$attendSeq."' OUTPUT";
		else 
			$query .= "@AttendanceMasterSeq = @AttendanceMasterSeq OUTPUT";
			
		return mssql_query($query, $this->msdb);
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
		
		return mssql_query($query, $this->msdb);
	}

}

$page = new Attendance;

//check if its a weekend.. cant mark roll on weekend
if(date("w") == 0 ||date("w") == 6)
	error(32);

if( is_numeric($_POST['daynum']) && is_numeric($_POST['pernum']) && $_POST['subcode'] )
{
	$page->startAttendanceInsert($_POST['subcode'], $_POST['pernum'], $_POST['daynum'], $_POST['classCampus']);
	
	$query = "";
	$stuid = "stud0";
	
	//ok so we check the attendance record if it exists good, if it dont we let it be created
	$attendSeq = $page->checkAttendance();
	$batchCode = rand(0,900000);

	for($i=0; isset($_POST[$stuid]); $i++)
	{
		//setup variable names
		$attend = "attend".$i;
		$abscode = "abscode".$i;
		$reason = "reason".$i;
		$stuid = "stud".$i;

		//insert them into database
		if($_POST[$attend] == 1 && $_POST[$stuid] > 0 )
			$query .= $page->insertAttendance($batchCode, $_POST[$stuid], 1, $_POST[$abscode], $_POST[$reason]);
		else if( $_POST[$stuid] > 0 )
			$query .= $page->insertAttendance($batchCode, $_POST[$stuid], 0, $_POST[$abscode], $_POST[$reason]);

		$query .= "; ";
	}

	$page->runQuery($query."SELECT 1");
	
	$page->runProc($batchCode, $attendSeq);
	
	
	
	$back = -2;
	include("../include/back.php");
}
else if(isset($_GET['code']) && $_GET['code'] && isStaff($_SESSION['gid']) )
{
	//display absentee page
	$page->displayStudentList(urldecode($_GET['code']), 0);
}
else if( $_SESSION['timetable'] )
{
	//they have a timetable so lets show them there subjects to pick for absentees
	$page->displayClassList(0);
}
else
{
	error(19);
}        
?>
