<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//display what has change since the last login for the user

require_once("include/permission.php");
require_once("include/involved.php");

class WhatsNew
{
	function WhatsNew()
	{
		//setup db
		$this->db = new dbManager;	
	}

	function submissionDetails($id)
	{
		//check for any new work
		$count['duework'] = $this->newDueWork($id);
		$count['retwork'] = $this->newReturnedWork($id);

		//check for new submissions in category submission boxes
		$subboxes = $this->newSubmission($id);

		$count['subboxes'] = $subboxes;
		return $count;
	}	

	function set_var($varName, $varContents)
	{
		$this->t_body->set_var($varName, $varContents);
	}
	
	function newEmail()
	{
		include_once("mail/mail.php");
		$mailbox = new mail;
		if( $mailbox->checkMailDetails() ) {
			$inbox = $mailbox->openMailBox();
			if($inbox)
 				return $mailbox->mailboxStatus($inbox);
		}
		else
			return false;
	}

	function lastCatVisit($id)
	{
		//get last visit to category
		$query = "SELECT datetime FROM homepage_log WHERE cid = '$id' AND user = '".$_SESSION['id']."'";
		$result = $this->db->getRow($query);
		if($result['datetime'])
			return $result['datetime'];
		else
			return "0000-00-00";
	}


	function lastDueWorkVisit()
	{
		//get the last visit by a student to due work
		$query = "SELECT datetime FROM submission_log WHERE page = '1' AND user = '".$_SESSION['id']."'";
		$result = $this->db->getRow($query);
		if($result['datetime'])
			return $result['datetime'];
		else
			return "0000-00-00";	
	}

	function lastReturnedWorkVisit()
	{
		//get last visit to returned work
		$query = "SELECT datetime FROM submission_log WHERE page = '2' AND user = '".$_SESSION['id']."'";
		$result = $this->db->getRow($query);
		if($result['datetime'])
			return $result['datetime'];
		else
			return "0000-00-00";
	}

	function newCategory($id)
	{

		$lastcatvisit = $this->lastCatVisit($id);

		//check if any new news has been submitted
		$query = "SELECT COUNT(*) AS newscount FROM subject_news WHERE deleted = 0 AND code = $id AND
			datetime_posted >= '$lastcatvisit' AND datetime_expires >= NOW()";
		$result = $this->db->getRow($query);
			
		$count['news'] = $result['newscount'];

		//check if any new files have been submitted
		$query = "SELECT COUNT(*) AS filecount FROM file WHERE 
			deleted = 0 AND category = $id AND submitted >= '$lastcatvisit'";
		$result = $this->db->getRow($query);
			
		$count['files'] = $result['filecount'];
			
		//check if any new links have been submitted
		$query = "SELECT COUNT(*) AS linkcount FROM link WHERE category = $id 
			AND submitted >= '$lastcatvisit'";
		$result = $this->db->getRow($query);

		$count['links'] = $result['linkcount'];
		
		//check if any new forum posts have been made
		$query = "SELECT COUNT(*) AS forumcount FROM forum WHERE cid = $id AND 
						deleted = 0 AND `date` >= '$lastcatvisit'";
		$result = $this->db->getRow($query);
		
		$count['forum'] = $result['forumcount'];				 
	
		return $count;
	}

	function newDueWork($id)
	{
		$lastvisit = $this->lastDueWorkVisit();
		
		//check for due work (* currently does not check if work has been returned *)
		$query = "SELECT COUNT(id) AS duework FROM submission_box WHERE category = '$id' AND created > '$lastvisit' AND owner != '".$_SESSION['id']."' AND deleted = 0"; 
		$result = $this->db->getRow($query);
		
		return $result['duework'];
	}

	function countDueWork($id)
	{
		//returns total active submission boxes for category

		$query = "SELECT COUNT(sub.id) as submitted, COUNT(box.id) as total FROM submission_box AS box 
			LEFT JOIN student_submission AS sub ON sub.box_id = box.id AND sub.owner = '".$_SESSION['id']."' 
			WHERE box.category = '$id' AND box.deleted = '0' AND box.owner != '".$_SESSION['id']."'";

		$result = $this->db->getRow($query);

		return $result['total'] - $result['submitted'];
	} 

	function newReturnedWork($id)
	{
		$lastvisit = $this->lastReturnedWorkVisit();
		
		//check for returned work
		$query = "SELECT COUNT(submission_return.id) AS retwork FROM submission_return, submission_box WHERE 
			submission_return.box_id = submission_box.id AND category = '$id' 
			AND submission_return.owner = '".$_SESSION['id']."' AND returned > '$lastvisit'"; 
		$result = $this->db->getRow($query);
		
		return $result['retwork'];
	}

	function countReturnedWork()
	{
		//check for returned work
		$query = "SELECT COUNT(submission_return.id) AS retwork FROM submission_return WHERE 
				owner = '".$_SESSION['id']."' AND deleted = '0'";

		$result = $this->db->getRow($query);
		
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

		$rh = $this->db->query($query);
		while( $subbox = $this->db->getNextArray($rh) )
		{

			//for each submission box check if there are any new submissions
			$query = "SELECT COUNT(id) as newsubs FROM student_submission 
				WHERE box_id = '".$subbox['id']."' AND submitted > '".$subbox['datetime']."'";

			$result = $this->db->getRow($query);
	
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

}
?>
