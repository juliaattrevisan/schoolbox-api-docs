<?php
require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/staffonly.php");

class ViewSubmissionPage {

	function ViewSubmissionPage()
	{
		$this->db = new dbManager;
		$this->db->dbConnect();
	}

	function setSubmissions($box_id)
	{

		//security check.. check that user owns this box and that its not deleted
		$query = "SELECT category.name, submission_box.description FROM submission_box, category WHERE 
		submission_box.category = category.id AND submission_box.owner = '".$_SESSION['id']."' 
		AND submission_box.id = '$box_id' 
		AND submission_box.deleted = '0'";
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() < 1)
		{
			error(17);
		}
		$desc = $this->db->dbGetNextArray();
		$this->t_body->set_var("ASSDESC", $desc['name']." - ".$desc['description']);

		//log page
		$this->logPageView($box_id);

		$query = "SELECT student_submission.id, user.firstname, user.lastname, user.id AS uid,
			DATE_FORMAT(submitted, '%a, %D of %b %l:%i%p') AS date, late, marked 
			FROM student_submission, user WHERE owner = user.id AND box_id = '$box_id' ORDER BY lastname";  
	
		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() > 0)
		{
			while($line = $this->db->dbGetNextArray() )
			{
				$this->t_body->set_var("OWNER", $line['lastname'].", ".$line['firstname']);
				$this->t_body->set_var("OWNER_ESCAPED", addslashes($line['lastname'].", ".$line['firstname']));
				$this->t_body->set_var("DATE", $line['date']);
				$this->t_body->set_var("VIEW", "sendframe.php?view=".$line['id']);
				$this->t_body->set_var("SAVE", "viewSubmission.php?view=".$line['id']."&save=1");
				$this->t_body->set_var("DELETE", "viewSubmission.php?delete=".$line['id']."&box_id=$box_id");
				if($line['late'])
					$this->t_body->set_var("COLOUR", "#ff0000");
				else
					$this->t_body->set_var("COLOUR", "#000000");
				if($line['marked'])
				{
					$this->t_body->set_var("MARKED","<img src=\"/images/greentick.gif\" width=\"13\" height=\"12\">");
					$this->t_body->set_var("RETURN", "");
				}
				else
				{
					$this->t_body->set_var("MARKED","<img src=\"/images/emptybox.gif\" border=\"0\" width=\"13\" height=\"12\">");
					$this->t_body->set_var("RETURN", "/ <a href=\"returnWork.php?box_id=$box_id&sid=".$line['uid']."\">Return</a>");
				}
				$this->t_body->parse("submission_listing","submitted_block",true);
			}
		}
		else
			$this->t_body->set_var("submission_listing", "No student has submitted work");

	}			

        function logPageView($box_id)
        {
                $testquery = "SELECT datetime FROM submission_log WHERE page = '3'  
                AND user = '".$_SESSION['id']."' AND sid = '$box_id'";
                
                $this->db->dbDoQuery($testquery);
                
                if($row = $this->db->dbGetNextArray() )
                {
                        $query = "UPDATE submission_log SET datetime = NOW() WHERE page =
                        '3' AND user = '".$_SESSION['id']."' AND sid = '$box_id'";
                }
                else            
                {
                        $query = "INSERT INTO submission_log (datetime, user, page, sid) VALUES 
				(NOW(), '".$_SESSION['id']."', '3', '$box_id')";
                }
                                        
                $this->db->dbDoQuery($query);
        }

	function sendWork($view, $save)
	{
		//sends students submission to owner of submission box only
		
		$result = $this->checkPerms($view);

		if($save)
			$attach = "attachment;";
		else
			$attach = "";

		//all checks completed.. continue with file send
		$filename = SUBMISSION_directory . "/" . $view . ".dat";

		//send headers
		header ("Content-Type: application/octet-stream"); 
		header ("Content-Disposition: $attach filename=\"".$result['lastname']."-".$result['realname']."\""); 
		header ("Content-Length: ".filesize($filename));
		readfile($filename);
	
	}

	function deleteWork($id)
	{
		//delete a student submission to a box so they can resubmit

		$this->checkPerms($id);

		//delete from database
		$query = "DELETE FROM student_submission WHERE student_submission.id = '$id'"; 
		$this->db->dbDoQuery($query);

		//all checks completed.. continue with file delete
                $filename = SUBMISSION_directory . "/" . $id . ".dat";
		unlink($filename);
		
	}

	function checkPerms($id)
	{
		//check user has access to student submission

		//check if user has rights to view file and get file details and username for appending to save
		$query = "SELECT student_submission.realname, user.lastname, user.firstname 
			FROM submission_box, student_submission, user 
			WHERE student_submission.id = '$id' AND student_submission.box_id = submission_box.id 
			AND submission_box.owner = '".$_SESSION['id']."' AND student_submission.owner = user.id";    	

		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0)
			$result = $this->db->dbGetNextArray();		
		else
		{
			error(44);
		}	
		
		return $result;
	}
  
        function showPage($box_id)
        {

		$this->t_body = new Template(TPL_directory);
               	$this->t_body->set_file("tplBODY", "submission/viewSub.htm");
		$this->t_body->set_var("TITLE", HEADING." - Student Submissions");
		$this->t_body->set_block("tplBODY", "submitted_block", "submission_listing");		
		$this->setSubmissions($box_id);

		include_once("../include/page.php");

                $this->t_body->pparse("finaloutput", "tplBODY");
        	
	}
}

$myPage = new ViewSubmissionPage();

if(isset($_GET['delete']) && $_GET['delete'])
{
	$myPage->deleteWork($_GET['delete']);
}

if(isset($_GET['box_id']) && $_GET['box_id'])
{
	$myPage->showPage($_GET['box_id']);
}
else if(isset($_GET['view']) && $_GET['view'])
{
	$myPage->sendWork($_GET['view'], $_GET['save']);
}
?>
