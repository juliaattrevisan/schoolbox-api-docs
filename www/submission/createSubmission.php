<?php

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/staffonly.php");
require_once("../include/html-time.php");

require_once("subbox.php");

class CreateSubmissionPage {
	function CreateSubmissionPage()
	{
                $this->t_body = new Template(TPL_directory);
               	$this->t_body->set_file("tplBODY", "submission/createSub.htm");		
	}

	function setDateFields($modify=0)
	{
		global $subbox;

		//this sets the date fields and allows people to only select months in the future
		//it also selects todays date by default
		//added feature to allow it to set current date those pulled from database in modify situation
		if($modify)
		{
			$details = $subbox->getSubBoxDetails($modify);

			//convert times into timestamps
			$date = strtotime($details['date']);
			$late_date = strtotime($details['late_date']);			

			$this->t_body->set_var("DATE", createDateList(date("j", $date), "date"));
			$this->t_body->set_var("MONTH", createMonthList(date("n", $date), "month"));
			$this->t_body->set_var("TIME", createTimeList(date("G", $date), "time"));
			$this->t_body->set_var("LDATE", createDateList(date("j", $late_date), "ldate"));
			$this->t_body->set_var("LMONTH", createMonthList(date("n", $late_date), "lmonth"));
			$this->t_body->set_var("LTIME", createTimeList(date("G", $late_date), "ltime"));

			$this->t_body->set_var("DESC", $details['description']);
			
		}
		else
		{
			
			//just set them to today
			//set variables in template
			
			$this->t_body->set_var("DATE", createDateList(date("j"), "date"));
			$this->t_body->set_var("MONTH", createMonthList(date("n"), "month"));
			$this->t_body->set_var("TIME",createTimeList(date("G"), "time"));

			$this->t_body->set_var("LDATE", createDateList(date("j"), "ldate"));
			$this->t_body->set_var("LMONTH",createMonthList(date("n"), "lmonth"));
			$this->t_body->set_var("LTIME",createTimeList(date("G"), "ltime"));

		}

	}
	
	function showCreate($fileid, $cid)
	{
		$this->t_body->set_var("TITLE", HEADING." - Create Submission Box");
		$this->t_body->set_var("HEADING", "Create");
		$this->setDateFields();

		//set hidden fields so they get submitted with form
		$this->t_body->set_var("FILEID", $fileid);
		$this->t_body->set_var("CID", $cid);
		$this->t_body->set_var("SID", "");
		
		//get file description to put into field
		$db = new dbManager;
		$db->dbConnect();
		$query = "SELECT description FROM file WHERE id = '$fileid'";
		$db->dbDoQuery($query);
		$file = $db->dbGetNextArray();
		$this->t_body->set_var("DESC", $file['description']);		

		$this->showPage();
	}
	
	function showModify($sid)
	{
		$this->t_body->set_var("TITLE", HEADING." - Modify Submission Box");
		$this->t_body->set_var("HEADING", "Modify");
		$this->setDateFields($sid);
		$this->t_body->set_var("SID", "<input type=\"hidden\" name=\"sid\" value=\"$sid\">");
				
		$this->showPage();	
	}
		
        function showPage()
        {

		include_once("../include/page.php");

                $this->t_body->pparse("finaloutput", "tplBODY");
        }
}

$myPage = new CreateSubmissionPage();
$subbox = new subBox;

if(isset($_POST['sid']) && isset($_POST['date']) && isset($_POST['time']) && isset($_POST['ldate']) && isset($_POST['ltime']) && 
	$_POST['date'] && $_POST['time'] && $_POST['ldate'] && $_POST['ltime'] && $_POST['sid'])
{
	//submission box id and times sent so update the submission box times
	$subbox->updateSubmissionDate($_POST['sid'], $_POST['date'], $_POST['month'], $_POST['time'], $_POST['ldate'], 
		$_POST['lmonth'], $_POST['ltime'], $_POST['description']);
	localRedir("/submission/listSubmission.php");
}
else if(isset($_POST['date']) && isset($_POST['time']) && isset($_POST['ldate']) && isset($_POST['ltime']) && $_POST['date'] && 
	$_POST['time'] && $_POST['ldate'] && $_POST['ltime'])
{
	//form has been submitted 
	$subbox->createSubmission($_POST['cid'], $_POST['date'], $_POST['month'], $_POST['time'], $_POST['ldate'], $_POST['lmonth'], 
			$_POST['ltime'], $_POST['fileid'], $_POST['description']);
	localRedir("/submission/listSubmission.php");
	
}
else if(isset($_GET['sid']) && $_GET['sid'])
{
	//display modfiy submission date page
	$myPage->showModify($_GET['sid']);
}
else if(isset($_GET['cid']) && isset($_GET['fileid']) && $_GET['cid'] && $_GET['fileid'])
{
	//display create submission box page
	//have recieved values from file submission page 
	$myPage->showCreate($_GET['fileid'], $_GET['cid']);
}
else
{
	//no information submitted something is wrong
	error(2);
} 

?>
