<?php

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/staffonly.php");
require_once("../include/html-time.php");

require_once("subbox.php");

class CreateSubmissionPage {
	function CreateSubmissionPage()
	{
				$this->db = new dbManager;
                $this->t_body = new Template(TPL_directory);
               	$this->t_body->set_file("tplBODY", "submission/createSub.htm");		
	}

	function setFileTypeList($type=0)
	{
		//get types of files that can be added
		$query = "SELECT * FROM file_type";
		$this->db->dbDoQuery($query);
		$typeHTML = "<OPTION VALUE=\"0\"></OPTION>";
		while($line = $this->db->dbGetNextArray())
		{
			if($line['id'] == $type)
			{
				$typeHTML .= "<OPTION VALUE=\"".$line['id']."\" selected>";
				$typeHTML .= $line['name'];
				$typeHTML .= "</OPTION>";
			}
			else
			{
				$typeHTML .= "<OPTION VALUE=\"".$line['id']."\">";
				$typeHTML .= $line['name'];
				$typeHTML .= "</OPTION>";
			}
		}
		$typeHTML = "<select name=\"type\" class=\"formfield\">".$typeHTML."</select>";
		$this->t_body->set_var("TYPELIST", $typeHTML);
	}	
	

	function setDateFields($modify=0)
	{
		global $subbox;

		//it selects todays date by default
		//added feature to allow it to set current date those pulled from database in modify situation
		if($modify)
		{
			$details = $subbox->getSubBoxDetails($modify);

			//convert times into timestamps
			$open_date = strtotime($details['open_date']);
			$due_date = strtotime($details['date']);
			$late_date = strtotime($details['late_date']);	
	
			$open_hour = date('g',$open_date);
			$open_min = date('i',$open_date);
			
			//output open dates
			$this->t_body->set_var("OPEN_DATE", date('d/m/Y', $open_date));
			$this->t_body->set_var("OPEN_HOUR", $open_hour);
			$this->t_body->set_var("OPEN_MIN", $open_min);
			if(date('a', $open_date) == 'pm')
				$this->t_body->set_var("OPEN_PM", "selected");
			else
				$this->t_body->set_var("OPEN_PM", "");
								
	
			$due_hour = date('g',$due_date);
			$due_min = date('i',$due_date);
			
			//output due dates
			$this->t_body->set_var("DUE_DATE", date('d/m/Y', $due_date));
			$this->t_body->set_var("DUE_HOUR", $due_hour);
			$this->t_body->set_var("DUE_MIN", $due_min);
			if(date('a', $due_date) == 'pm')
				$this->t_body->set_var("DUE_PM", "selected");
			else
				$this->t_body->set_var("DUE_PM", "");
			
			//output late dates
			$late_hour = date('g',$late_date);
			$late_min = date('i',$late_date);
			$this->t_body->set_var("LATE_DATE", date('d/m/Y', $late_date));
			$this->t_body->set_var("LATE_HOUR", $late_hour);
			$this->t_body->set_var("LATE_MIN", $late_min);
			if(date('a', $late_date) == 'pm')
				$this->t_body->set_var("LATE_PM", "selected");
			else
				$this->t_body->set_var("LATE_PM", "");
				
			$this->t_body->set_var("DESC", $details['description']);
			
		}
		else
		{
			//just set them to today + 1 hour
			//set variables in template
			$defaultduetime = strtotime('+1 hours');
			
			$this->t_body->set_var("OPEN_DATE", date('d/m/Y'));
			$this->t_body->set_var("OPEN_HOUR", date('g', $defaultduetime));
			$this->t_body->set_var("OPEN_MIN", '00');
			if(date('a', $defaultduetime) == 'pm')
				$this->t_body->set_var("OPEN_PM", "selected");
			else
				$this->t_body->set_var("OPEN_PM", "");
				
			$this->t_body->set_var("DUE_DATE", date('d/m/Y'));
			$this->t_body->set_var("DUE_HOUR", date('g', $defaultduetime));
			$this->t_body->set_var("DUE_MIN", '00');
			if(date('a', $defaultduetime) == 'pm')
				$this->t_body->set_var("DUE_PM", "selected");
			else
				$this->t_body->set_var("DUE_PM", "");
			
			$this->t_body->set_var("LATE_DATE", date('d/m/Y'));
			$this->t_body->set_var("LATE_HOUR", date('g', $defaultduetime));
			$this->t_body->set_var("LATE_MIN", '00');
			if(date('a', $defaultduetime) == 'pm')
				$this->t_body->set_var("LATE_PM", "selected");
			else
				$this->t_body->set_var("LATE_PM", "");

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
		
		$this->setFileTypeList();		

		$this->showPage();
	}
	
	function showModify($sid)
	{
		$this->t_body->set_var("TITLE", HEADING." - Modify Submission Box");
		$this->t_body->set_var("HEADING", "Modify");
		$this->setDateFields($sid);
		$this->t_body->set_var("SID", "<input type=\"hidden\" name=\"sid\" value=\"$sid\">");
		
		global $subbox;
		$details = $subbox->getSubBoxDetails($sid);
		$this->setFileTypeList($details['type']);
				
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

if($_POST['due_date'] && $_POST['due_hour'] && $_POST['late_date'] && $_POST['late_hour'] && $_POST['sid'])
{
	//submission box id and times sent so update the submission box times
	$subbox->updateSubmissionDate($_POST['sid'], 
		$_POST['open_date'], $_POST['open_hour'], $_POST['open_minute'], $_POST['open_period'],
		$_POST['due_date'], $_POST['due_hour'], $_POST['due_min'], $_POST['due_period'], 
		$_POST['late_date'], $_POST['late_hour'], $_POST['late_min'], $_POST['late_period'], 
		$_POST['description'], $_POST['type']);
	localRedir("/submission/listSubmission.php");
}
else if($_POST['due_date'] && $_POST['due_hour'] && $_POST['late_date'] && $_POST['late_hour'])
{
	//form has been submitted 
	$subbox->createSubmission($_POST['cid'], 
			$_POST['open_date'], $_POST['open_hour'], $_POST['open_minute'], $_POST['open_period'],
			$_POST['due_date'], $_POST['due_hour'], $_POST['due_minute'], $_POST['due_period'], 
			$_POST['late_date'], $_POST['late_hour'], $_POST['late_min'], $_POST['late_period'], 
			$_POST['fileid'], $_POST['description'], $_POST['type']);
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
