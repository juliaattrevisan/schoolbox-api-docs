<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//displays modDet allows staff to add or modify a detention for an MGS student to the detention database 

require_once("../../include/session.php");
require_once("../../include/include.php");
require_once("../../include/permission.php");
require_once("detperm.php");
require_once("studSearch.php");
require_once("detNav.php");  // 20 Jan 2005
require_once("sql/modDet.php");

class attend
{
	function attend()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "modules/detention/attend.htm");
		$this->t_body->set_block("tplBODY", "class", "class_sect");
		$this->t_body->set_block("class", "class_list", "class_listing");
		$this->t_body->set_block("tplBODY", "student", "student_sect");
		$this->t_body->set_block("student", "student_list", "student_listing");
		$this->t_body->set_block("tplBODY", "studentprint", "studentprint_sect");
		$this->t_body->set_block("studentprint", "studentprint_list", "studentprint_listing");
		require_once("../../include/page.php");
		$this->t_body->set_var("TITLE", "Detention System Attendances");
		$this->perm = permType();
	}

	function displayClassList()
	{
		$myDet = new det();
		if ($this->perm == 4)
			$su = 1;
		else
			$su = 0;
		$classList = $myDet->getClasses($su);
		if ($classList){

			$temp = array();

			foreach ($classList as $class){
				$studs = $myDet->getStudents($class['id']);
				if ($studs && $class != $temp){
					$this->t_body->set_var("DATE", $class['date']);
					$this->t_body->set_var("LOCATION", $class['location']);
					$this->t_body->set_var("TYPE", $class['type']);
					$this->t_body->set_var("CLASSID", $class['id']);
					if (defined('SHOW_CLASS_LISTS'))
						$this->t_body->set_var("FILE", "classlists");
					else
						$this->t_body->set_var("FILE", "attend");
					$this->t_body->parse("class_listing", "class_list", true);

					$temp = $class;
					$parsed = 1;
				}
				//else
 					//$myDet->attendComplete($class['id']);	
			}
		}
		else {
			$this->t_body->set_var("class_listing", "<tr><td width=\"5%\">&nbsp;</td><td colspan=\"4\">No detention classes retrieved</td></tr>");
		}
		if (defined('SHOW_CLASS_LISTS')) {
			$this->t_body->set_var("HEADER", "Detention lists");
			$this->t_body->set_var("ACTION", "View");
			$this->t_body->set_var("PLEASENOTE", "Please note: these lists are subject to change.");
		} else {
			if ($su)
				$this->t_body->set_var("HEADER", "Attendances");
			else
				$this->t_body->set_var("HEADER", "Attendances for ".$class['stafffname']." ".$class['stafflname']);
			$this->t_body->set_var("ACTION", "Mark Attendances");
			$this->t_body->set_var("PLEASENOTE", "Please note: attendances cannot be marked until after the detention has started.");
		}
		if (!$parsed) {
			$this->t_body->set_var("class_listing", "<tr><td width=\"5%\">&nbsp;</td><td colspan=\"4\">No detention classes retrieved</td></tr>");
		}
		$nav = new detNav($this->perm);
		$this->t_body->set_var("DETNAV", $nav->getNav());  // M. Parncutt 20 Jan 2005
		$this->t_body->set_var("student_sect", "");
		$this->t_body->set_var("studentprint_sect", "");
		$this->t_body->parse("class_sect", "class", true);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function printStudentList($classid)
	{
		$date_stamp = date("Ymd");
		$file_name = "Detention List_$date_stamp.csv";
		//to open directly into excel
		header("Content-Type: application/x-msexcel");
		//to force download
		header("Content-Disposition: attachment; filename=$file_name");
		//spreadsheet labels
		$column_labels = array("Surname", "Firstname", "Offence", "Workset", "Notes", "Attended", "Reason");
		foreach ($column_labels as $value) {
			echo("$value,");
		}
		echo("\r\n");
		$myDet = new det();
		$studList = $myDet->getStudents($classid);
		foreach ($studList as $stud) {
			//replace commas with spaces for excel to display properly
			echo(strtr($stud['studlname'], ",", " ").",");
			echo(strtr($stud['studfname'], ",", " ").",");
			echo(strtr($stud['offence'], ",", " ").",");
			echo(strtr($stud['workset'], ",", " ").",");
			echo(strtr($stud['history'], ",", " ").",,");
			echo("\r\n");
		}		
	}
	
	// Mark Parncutt 24 Jan 2004
	// Displays det lists for upcoming dets in a printable format
	function displayStudPrintList($classid)
	{
		$this->t_body->set_var("DISPLAYED", 1);
		$myDet = new det();
		$studList = $myDet->getStudents($classid);
		if ($studList){
			foreach ($studList as $stud){
				$this->t_body->set_var("SURNAME", $stud['studlname']);
				$this->t_body->set_var("FIRSTNAME", $stud['studfname']);
				$this->t_body->set_var("HOUSE", $stud['house']);
				$this->t_body->set_var("YEAR", $stud['year']);
				$this->t_body->parse("studentprint_listing", "studentprint_list", true);
			}
		}
		else {
			$this->t_body->set_var("studentprint_listing", "No students were assigned to this detention class");
		}
		$this->t_body->set_var("HEADER", "Detention lists");
		$this->t_body->set_var("DETINFO", "Detention ".$stud['date'].", ".$stud['location']." - ".$stud['stafffname']." ".$stud['stafflname']);

		$nav = new detNav($this->perm);
		$this->t_body->set_var("DETNAV", $nav->getNav());
		$this->t_body->parse("studentprint_sect", "studentprint", true);
		$this->t_body->set_var("class_sect", "");
		$this->t_body->set_var("student_sect", "");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
	// End M. Parncutt

	function displayStudList($classid)
	{
		$this->t_body->set_var("DISPLAYED", 1);
		$myDet = new det();
		$studList = $myDet->getStudents($classid);
		$i=0;
		if ($studList){
			foreach ($studList as $stud){
				$this->t_body->set_var("SURNAME", $stud['studlname']);
				$this->t_body->set_var("FIRSTNAME", $stud['studfname']);
				$this->t_body->set_var("YEAR", $stud['year']);    // Mark Parncutt 27 Jan 2005
				$this->t_body->set_var("HOUSE", $stud['house']);  // Mark Parncutt 27 Jan 2005
				$this->t_body->set_var("DETID", $stud['detid']);
				$this->t_body->set_var("DETDATE", $stud['detDate']);
				$this->t_body->set_var("ATTEND", "checked");
				$this->t_body->set_var("NUM", $i);
				$this->t_body->parse("student_listing", "student_list", true);
				$i++;
			}
		}
		else {
			$this->t_body->set_var("student_listing", "No students were assigned to this detention class");
		}
		$this->t_body->set_var("HEADER", "Mark attendences");
		$this->t_body->set_var("DETINFO", "Detention ".$stud['date'].", ".$stud['location']." - ".$stud['stafffname']." ".$stud['stafflname']);

		$nav = new detNav($this->perm);						// M. Parncutt 20 Jan 2005
		$this->t_body->set_var("DETNAV", $nav->getNav());   // M. Parncutt 20 Jan 2005
		$this->t_body->parse("student_sect", "student", true);
		$this->t_body->set_var("class_sect", "");
		$this->t_body->set_var("studentprint_sect", "");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
}


if (permType() >= 2){

  $page = new attend();
  //process the students attendance
  if ($_GET['print'] && isset($_GET['print']))
  {
   	$page->printStudentList($_GET['classid']);
  }
  else if (($_POST['detid_0'] && isset($_POST['detid_0'])) && ($_POST['detdate'] || isset($_POST['detdate'])))
  {
	$myDet = new det();
	
	for($i = 0; $_POST["detid_$i"]; $i++)
	{
		$myDet->attended($_POST["detid_$i"], $_POST["attend_$i"], $POST["reason_$i"]);
	}

 	$myDet->attendComplete($_POST['detdate']);
	$page->displayClassList();
  }
  //display student list for that class
  else if ($_GET['classid'] || isset($_GET['classid']))
  {
    if (defined('SHOW_CLASS_LISTS'))
		$page->displayStudPrintList($_GET['classid']);
    else
		$page->displayStudList($_GET['classid']);
  }
  //display class list for this teacher
  else
  {
	$page->displayClassList(); 
  }
}
//if staff member or prefect show search/add page
else if (isStaff($_SESSION['gid']) || permType() == 1)
{
 	$myAdd = new detSearch;
        $myAdd->displayFindStud();
}
//if student and no permissions then error
else
	error(14); 
?>
