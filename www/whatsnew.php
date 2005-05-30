<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//display what has change since the last login for the user

require_once("include/session.php");
require_once("include/include.php");
require_once("include/permission.php");
require_once("include/involved.php");

class OutPut
{
	function OutPut()
	{

		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "whatnew.htm");

		$this->t_body->set_var("TITLE", HEADING." - Whats New");
		
		include("page.php");

		$this->t_body->set_block("tplBODY", "subject_block", "subject");	
		$this->t_body->set_block("tplBODY", "group_block", "group");	

		//setup db
		$this->db = new dbManager;
		$this->db->dbConnect();

		//first lets check email
		$this->newEmail();

		if(isStudent($_SESSION['gid']))
		{
			$this->duevisit = $this->lastDueWorkVisit();
			$this->retvisit = $this->lastReturnedWorkVisit();
		
			if($this->duevisit == "")
				$this->duevisit = "0000-00-00 00:00:00";
			if($this->retvisit == "")
				$this->retvisit = "0000-00-00 00:00:00";
		}


		$catList = involvedCategoryList();
	
		$st = 0;
		//for each subject
		for($x = 0; $catList['code'][$x] ; $x++)
		{
			if($this->outputCategoryDetails("SUBJECT", $catList['id'][$x], $catList['name'][$x]))
				$st++;
			//$this->totalDue += $this->countDueWork($catList['id'][$x]); 
			//$this->totalRet += $this->countReturnedWork($catList['id'][$x]); 
		}

		if($st == 0)
			$this->t_body->set_var("subject","");
		
		$st = 0;

		//for each group
		for(; $catList['id'][$x] ; $x++)
		{
			if($this->outputCategoryDetails("GROUP", $catList['id'][$x], $catList['name'][$x]))
				$st++;
			//$this->totalDue += $this->countDueWork($catList['id'][$x]); 
			//$this->totalRet += $this->countReturnedWork($catList['id'][$x]); 

		}

		if($st == 0)
			$this->t_body->set_var("group","");

		
		$this->set_var("NEW_SUB", $this->totalNewSub);
		$this->set_var("NEW_DUE", $this->totalNewDue);
		$this->set_var("NEW_RET", $this->totalNewRet);
		$this->set_var("DUE", $this->totalDue);
		$this->set_var("RET", $this->totalRet);
		
	}


	function outputCategoryDetails($out, $id, $name)
	{
			$this->set_var($out."_NAME", $name);
	
			$count = $this->newCategory($id);
		
			if($this->duevisit != "")
				$duework = $this->newDueWork($id, $this->duevisit);
				
			if($this->retvisit != "")
				$retwork = $this->newReturnedWork($id, $this->retvisit);

			//check for new submissions in category submission boxes
			$subArray = $this->newSubmission($id);

			$this->totalNewDue += $duework;
			$this->totalNewRet += $retwork;

			if($duework == 1)
				$output .= "<font color=\"#FF0000\">You have $duework new task to complete</font><br>";
			else if($duework > 1)
				$output .= "<font color=\"#FF0000\">You have $duework new tasks to complete</font><br>";
			
			if($retwork == 1)
				$output .= "You have $retwork new returned task<br>";
			else if($retwork > 1)
				$output .= "You have $retwork new returned tasks<br>";
			
			if(is_array($subArray))
			{
				while($sub = array_pop($subArray) )
				{ 			
					if($sub['count'] == 1)
						$output .= "There is 1 new submission in ".$sub['description']."<br>";
					else if($sub['count'] > 1)
						$output .= "There are ".$sub['count']." new submissions in ".$sub['description']."<br>";
				}
			}	

			if($count['news'] == 1)
				$output .= "There is ".$count['news']." new news item<br>";
			else if($count['news'] > 1)
				$output .= "There are ".$count['news']." new news items<br>";
			
			if($count['files'] == 1)
				$output .= "There is ".$count['files']." new file<br>";
			else if($count['files'] > 1)
				$output .= "There are ".$count['files']." new files<br>";
			
			if($count['links'] == 1)
				$output .= "There is ".$count['links']." new link<br>";
			else if($count['links'] > 1)
				$output .= "There are ".$count['links']." new links<br>";


			if($output)
			{
				$this->set_var($out."_TEXT", $output);
				$this->t_body->parse(strtolower($out), strtolower($out)."_block", true);
				return 1;
			}
		return 0;
	}

	function set_var($varName, $varContents)
	{
		$this->t_body->set_var($varName, $varContents);
	}
	
	function newEmail()
	{
		include_once("mail/mail.php");

		$mailbox = new mail;
		$mailbox->checkMailDetails();
		$inbox = $mailbox->openMailBox();
 
		$check = imap_mailboxmsginfo($inbox);

		$this->set_var("NEW_EMAIL", $check->Unread);
		//$this->set_var("EMAIL_RECENT", $check->Recent);
		$this->set_var("EMAIL", $check->Nmsgs);
		
	}

	function lastCatVisit($id)
	{
		//get last visit to category
		$query = "SELECT datetime FROM homepage_log WHERE cid = '$id' AND user = '".$_SESSION['id']."'";
		$this->db->dbDoQuery($query);
		$result = $this->db->dbGetNextArray();

		return $result['datetime'];
	}


	function lastDueWorkVisit()
	{
		//get the last visit by a student to due work
		$query = "SELECT datetime FROM submission_log WHERE page = '1' AND user = '".$_SESSION['id']."'";
		$this->db->dbDoQuery($query);
		$result = $this->db->dbGetNextArray();

		return $result['datetime'];			
	}

	function lastReturnedWorkVisit()
	{
		//get last visit to returned work
		$query = "SELECT datetime FROM submission_log WHERE page = '2' AND user = '".$_SESSION['id']."'";
		$this->db->dbDoQuery($query);
		$result = $this->db->dbGetNextArray();

		return $result['datetime'];
	}

	function newCategory($id)
	{

		$lastcatvisit = $this->lastCatVisit($id);

		//check they have actually visited
		if($lastcatvisit == "")
		{
			//have not visited homepage before
			$lastcatvisit = 0;
		}

		//check if any new news has been submitted
		$query = "SELECT COUNT(*) AS newscount FROM subject_news WHERE deleted = 0 AND code = '$id' AND
			datetime_posted > '$lastcatvisit'";
		$this->db->dbDoQuery($query);
		$result = $this->db->dbGetNextArray();
			
		$count['news'] = $result['newscount'];

		//check if any new files have been submitted
		$query = "SELECT COUNT(*) AS filecount FROM file WHERE 
			deleted = '0' AND category = '$id' AND submitted > '$lastcatvisit'";
		$this->db->dbDoQuery($query);
		$result = $this->db->dbGetNextArray();
			
		$count['files'] = $result['filecount'];
			
		//check if any new links have been submitted
		$query = "SELECT COUNT(*) AS linkcount FROM link WHERE category = '$id' 
			AND submitted > '$lastcatvisit'";
		$this->db->dbDoQuery($query);
		$result = $this->db->dbGetNextArray();

		$count['links'] = $result['linkcount'];
	
		return $count;
	}

	function newDueWork($id, $lastvisit)
	{
		//check for due work (* currently does not check if work has been returned *)
		$query = "SELECT COUNT(id) AS duework FROM submission_box WHERE category = '$id' AND created > '$lastvisit'"; 
		$this->db->dbDoQuery($query);
		$result = $this->db->dbGetNextArray();
		
		return $result['duework'];
	}

	function countDueWork($id)
	{
		//returns total active submission boxes for category

		$query = "SELECT COUNT(sub.id) as submitted, COUNT(box.id) as total FROM submission_box AS box 
			LEFT JOIN student_submission AS sub ON sub.box_id = box.id AND sub.owner = '".$_SESSION['id']."' 
			WHERE box.category = '$id' AND box.deleted = '0' AND box.owner != '".$_SESSION['id']."'";

		$this->db->dbDoQuery($query);
		$result = $this->db->dbGetNextArray();

		return $result['total'] - $result['submitted'];

	} 


	function newReturnedWork($id, $lastvisit)
	{
		//check for returned work
		$query = "SELECT COUNT(submission_return.id) AS retwork FROM submission_return, submission_box WHERE 
			submission_return.box_id = submission_box.id AND category = '$id' 
			AND submission_return.owner = '".$_SESSION['id']."' AND returned > '$lastvisit'"; 
		$this->db->dbDoQuery($query);
		$result = $this->db->dbGetNextArray();
		
		return $result['retwork'];
	}

	function countReturnedWork()
	{
		//check for returned work
		$query = "SELECT COUNT(submission_return.id) AS retwork FROM submission_return WHERE 
				owner = '".$_SESSION['id']."' AND deleted = '0'";

		$this->db->dbDoQuery($query);
		$result = $this->db->dbGetNextArray();
		
		return $result['retwork'];
	}


	function newSubmission($category)
	{
		//$returnArray = array();
		
		//select all users submission boxes which are not deleted and still open
		$query = "SELECT description, id, datetime 
			FROM submission_box LEFT JOIN submission_log ON submission_log.page = '3' 
			AND submission_box.id = submission_log.sid 
			WHERE owner = '".$_SESSION['id']."' AND deleted = '0' AND category = '$category'";

		$rh = $this->db->dbDoQuery($query);
		while( $subbox = $this->db->dbGetNextArray($rh) )
		{

			//for each submission box check if there are any new submissions
			$query = "SELECT COUNT(id) as newsubs FROM student_submission 
				WHERE box_id = '".$subbox['id']."' AND submitted > '".$subbox['datetime']."'";

			$this->db->dbDoQuery($query);
			$result = $this->db->dbGetNextArray();
	
			//if there are any new submissions put them in a array for return
			if($result['newsubs'] > 0 )
			{
				$returnArray[$subbox['id']]['description'] = $subbox['description'];
				$returnArray[$subbox['id']]['count'] = $result['newsubs'];
				
				//also keep count of total (for efficency)
				$this->totalNewSubs += $subbox['count'];			

			}
		}
		return $returnArray;
	}

	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

}

$myPage = new OutPut();


$myPage->showPage();
?>
