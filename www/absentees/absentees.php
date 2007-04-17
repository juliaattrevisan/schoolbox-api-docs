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

class Absentee
{
	function Absentee($absperiod = "")
	{
		$this->year = date("Y");
		$this->date = date("Y-m-d");
		$this->campus = 'G';
		$this->period = $absperiod;
		
		$this->msdb = @mssql_connect(MSSQL_host, MSSQL_user, MSSQL_passwd);
	}

	function displayStudentList($subCode)
        {
                $this->t_body = new Template(TPL_directory);
                $this->t_body->set_file("tplBODY", "absentees/studentList.htm");
                $this->t_body->set_block("tplBODY", "student_list", "student_list_block");
                $this->t_body->set_var("TITLE", HEADING." - Attendances Roll");
                include_once("../include/page.php");

                $this->t_body->set_var("DAYNUMBER", $subCode);
                $this->t_body->set_var("PERNUMBER", $this->period);
                $this->t_body->set_var("SUBCODE", $subCode);   
                $this->t_body->set_var("DATE",  date("j/n/Y"));

                $this->t_body->set_var("SUBJECTNAME", "Homeroom");

		$query = "
                        SELECT CommunityStudents.Surname,
                                CommunityStudents.Preferred,
                                Students.ID,
                                Absences.AbsenceType,       
                                Absences.Description
                        FROM    Students Students
                        INNER JOIN StudentSemester ON Students.ID = StudentSemester.ID
                        LEFT JOIN StudentClasses ON (
                                StudentSemester.FileYear = StudentClasses.FileYear AND
                                StudentSemester.FileSemester = StudentClasses.FileSemester AND
                                StudentSemester.ID = StudentClasses.ID)
                        LEFT JOIN SubjectClasses ON (
                                StudentClasses.FileType = SubjectClasses.FileType AND
                                StudentClasses.FileYear = SubjectClasses.FileYear AND
                                StudentClasses.FileSemester = SubjectClasses.FileSemester AND
                                StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND
                                StudentClasses.ClassCode = SubjectClasses.ClassCode )
                        LEFT JOIN Community CommunityStudents ON
                                Students.ID = CommunityStudents.ID
                        LEFT JOIN luStudentStatus lS ON (
                                StudentSemester.Status = lS.Code)
                        LEFT JOIN Absences ON (
                                Students.ID = Absences.ID AND
                                Absences.AbsenceDate = '" . $this->date . "' AND
				Absences.AbsencePeriod = '".$this->period."' AND
				Absences.AbsencePeriodSort = '".$this->period."')
                        WHERE   CommunityStudents.DeceasedFlag = 0
                                AND lS.SynergyMeaning <> 'LEFT'
                                AND lS.SynergyMeaning <> 'LOA'
                                AND StudentClasses.ClassCode = '" . $subCode . "'
                                AND (SubjectClasses.FileYear='" . $_SESSION['FileYear']  . "')
                                AND (SubjectClasses.FileSemester='" . $_SESSION['FileSemester'] .  "')
                ORDER BY CommunityStudents.Surname, CommunityStudents.Preferred ";

 		$result = mssql_query($query, $this->msdb);

                $x=0;

                while($student = @mssql_fetch_array($result))
                {
                        $this->t_body->set_var("SURNAME", $student[0]);
                        $this->t_body->set_var("FIRSTNAME", $student[1]);


                        //now the absentee stuff
                        $abscode = $this->createAbsCodeHTML($student['AbsenceType']);

                        $this->t_body->set_var("ABSCODENAME", "abscode".$x);
                        $this->t_body->set_var("ABSCODE", $abscode);
                        $this->t_body->set_var("ATTENDNAME", "attend".$x);

                        if($student['AbsenceType'])
                                $this->t_body->set_var("ATTENDED", "");
                        else
                                $this->t_body->set_var("ATTENDED", "checked");

                        $this->t_body->set_var("REASONNAME", "reason".$x);
                        $this->t_body->set_var("REASON", $student['PossibleDescription']);
                        $this->t_body->set_var("STUDNAME", "stud".$x);
                        $this->t_body->set_var("STUDCODE", $student['ID']);


                        $this->t_body->parse("student_list_block", "student_list", true);
                        $x++;
                }

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

 	function displayClassList()
        {
                $this->t_body = new Template(TPL_directory);
                $this->t_body->set_file("tplBODY", "absentees/classList.htm");
                $this->t_body->set_block("tplBODY", "class_list", "class_list_block");
                $this->t_body->set_var("TITLE", HEADING." - Attendances Class List");
                include_once("../include/page.php");

                //setup database
                $this->msdb = @mssql_connect(MSSQL_host,MSSQL_user,MSSQL_passwd);

                $synergy_id = $_SESSION['sid'];

                $this->t_body->set_var("DAYNO", "");
                $this->t_body->set_var("DATE", date("j/n/Y"));
       
                //get teacher name
                $mydb = new dbManager;
                $mydb->dbConnect();
                $query = "SELECT title, firstname, lastname FROM user WHERE id = '".$_SESSION['id']."'";
                $mydb->dbDoQuery($query);
                $teacher = $mydb->dbGetNextArray();

                $this->t_body->set_var("TEACHERNAME", formatName(NULL, $teacher));

		$query = "SELECT Timetable.ClassCode
                          FROM SynergyOne.dbo.SubjectClasses SubjectClasses, SynergyOne.dbo.Timetable Timetable
                          WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND
                          Timetable.ClassCode = SubjectClasses.ClassCode AND Timetable.FileSemester =
                          SubjectClasses.FileSemester AND Timetable.FileType = SubjectClasses.FileType
                          AND Timetable.FileYear = SubjectClasses.FileYear AND
                          ((SubjectClasses.FileSemester='".$_SESSION['FileSemester']."') AND (SubjectClasses.FileYear='".$_SESSION['FileYear']."')
                          AND (Timetable.StaffID='$synergy_id')) AND (SubjectClasses.ClassCode like '%HOME%')
			";

                $result = mssql_query($query, $this->msdb);
		if ($result != 1){
                	$code = mssql_fetch_array($result);
 			$period = array("AM", "PM");
                	foreach( $period as $per )
                	{
                        	//output list of classes
                        	$this->t_body->set_var("ROOM", "");
                        	$this->t_body->set_var("DESC", "Homeroom");
                        	$this->t_body->set_var("PERNO", $per);
                        	$this->t_body->set_var("CODE", $code['ClassCode']);
                       		$this->t_body->set_var("SUBJECTLINK", "absentees.php?period=$per&code=".$code['ClassCode']);

                        	$this->t_body->parse("class_list_block", "class_list", true);
                	}
                	$this->t_body->pparse("finaloutput", "tplBODY");
		}
		else
			error(19);
        }

 	function involvedInSubjectSyn($code)
        {      
                //returns 1 when user is involved in subject according to synergy
                //setup database

                $query ="SELECT StaffID 
                         FROM SynergyOne.dbo.Timetable Timetable
                         WHERE Timetable.FileSemester='".$_SESSION['FileSemester']."' AND Timetable.FileYear='".$_SESSION['FileYear']."'
                         AND Timetable.StaffID = '".$_SESSION['sid']."' AND Timetable.ClassCode = '$code'
		";

                $result = mssql_query($query, $this->msdb);

                if ($result != 1){
                	if(mssql_num_rows($result) > 0)
                        	return 1;
                	else
                        	return 0;
		}
		else
			return 0;
        }

	function insertAbsence($studid, $abstype, $reason)
	{ 
		if ($this->period == "PM")
			$seq = 2;
		else
			$seq = 1;
		$query = "INSERT SynergyOne.dbo.Absences
			  (ID, AbsenceDate, AbsencePeriod, AbsencePeriodSort, AbsenceType, Description, AbsenceSeq)
			  VALUES
			  ('$studid', '".$this->date."', '".$this->period."', '".$this->period."', '$abstype', '$reason', '$seq')
			";
		mssql_query($query, $this->msdb);	

	}
	
	function deleteAbsence($stuid){
		$query = "DELETE FROM SynergyOne.dbo.Absences
			  WHERE	ID = '$stuid'
			  AND	AbsenceDate = '".$this->date."'
			  AND	AbsencePeriod = '".$this->period."'
			";
		mssql_query($query, $this->msdb);   
	}
	
	function updateAbsence($studid, $abstype, $reason)
	{
		$query = "UPDATE SynergyOne.dbo.Absences
			  SET AbsenceType = '$abstype',
			  Description = '$reason',
			  AbsencePeriod = '".$this->period."'
			  WHERE ID = '$studid' AND
			  AbsenceDate = '".$this->date."' AND
			  AbsencePeriod = '".$this->period."'
			";
		mssql_query($query, $this->msdb);			 
	}

	function checkAbsent($stuid)
	{
		//checks if studnet has already been marked as absent
		$query = "SELECT AbsencePeriod FROM SynergyOne.dbo.Absences
			  WHERE ID = '$stuid' AND
			  AbsenceDate = '".$this->date."' AND
			  AbsencePeriod = '".$this->period."'
			";
		$result = mssql_query($query, $this->msdb);
		if ($result != 1){
			if ($per = mssql_fetch_array($result)){
				return $per['AbsencePeriod'];
			}
			else
				return 0;
		}
		else
			return 0;
	}
}
         

$page = new Absentee;
//check if its a weekend.. cant mark roll on weekend
if(date("w") == 0 ||date("w") == 6)    
        error(32);

if (isset($_POST['submit']) && $_POST['submit'] && isset($_POST['pernum']) && $_POST['pernum'])
{

	$stuid = "stud0";
	$page = new Absentee($_POST['pernum']);

	for($i=0; isset($_POST[$stuid]); $i++)
	{
		//setup variable names
		$attend = "attend".$i;
		$abscode = "abscode".$i;
		$reason = "reason".$i;
		$stuid = "stud".$i;	
		
		//check if student is absent, if so, update or insert absence into db
		if ($page->checkAbsent($_POST[$stuid])){

			if ($_POST[$attend] == 0){
				//update existing record
				$page->updateAbsence($_POST[$stuid], $_POST[$abscode], $_POST[$reason]); 
			}
			else {
				//delete item from synergy
				$page->deleteAbsence($_POST[$stuid]);
			}
		}
		else {
			if ($_POST[$attend] == 0) {
				//insert them into db
				$page->insertAbsence($_POST[$stuid], $_POST[$abscode], $_POST[$reason]);
			}
		}
	}
	$back = -2;
	include("../include/back.php");
}
else if (isset($_GET['code']) && $_GET['code'] && isStaff($_SESSION['gid']) && $page->involvedInSubjectSyn($_GET['code'])) 
{
	//display absentee page
	$page = new Absentee($_GET['period']);
        $page->displayStudentList(urldecode($_GET['code']));
}
else if( $_SESSION['timetable'] )
{
        //they have a timetable so lets show them their subjects to pick for absentees
	$page = new Absentee;
        $page->displayClassList();
}
else
{
	error(19);
}

?>
