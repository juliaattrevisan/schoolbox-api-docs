<?php

require_once("../include/session.php");
require_once("../include/include.php");

class dueWorkPage 
{
	function dueWorkPage()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "submission/dueWork.htm");
		$this->t_body->set_var("TITLE", HEADING." - Due Work");
		$this->t_body->set_block("tplBODY", "due_block", "due_list"); 
		$this->t_body->set_block("tplBODY", "category_block", "category_listing");				
		
		include_once("../include/page.php");

		$this->db = new dbManager;
		$this->db->dbConnect();

		$this->logPageView();
	}

	function getDueWork()
	{
		$now = date("U");	
		require_once("../include/fileTypes.php");
		require_once("../include/involved.php");
		$catList = involvedCategoryList(1);

		//for each subject code do a query and get 
		foreach($catList['id'] as $catId)
		{

			$query = "SELECT file.description, file.realname, file.id AS fid, 
				DATE_FORMAT(submission_box.date, '%a, %D of %b %l:%i%p') AS date, submission_box.id, category.name, 
				UNIX_TIMESTAMP(submission_box.date) AS overdue_date, student_submission.owner, file.mime_type 
				FROM file, submission_box, category LEFT JOIN student_submission
				ON student_submission.box_id = submission_box.id AND student_submission.owner = ".$_SESSION['id']."
				WHERE submission_box.fid = file.id AND submission_box.category = category.id 
				AND submission_box.deleted = '0' AND category.id = '$catId' 
				AND submission_box.late_date > NOW()
				ORDER BY submission_box.date";
	
			$res = $this->db->dbDoQuery($query);

			if($this->db->dbCountRows() > 0)
			{
				$completed = 0;
				$due = $this->db->dbCountRows($res);
				
				while($result = $this->db->dbGetNextArray($res) )
				{
					//if has not submitted response then output it
					if($result['owner'] == "")
					{
						if($result['overdue_date'] < $now)
							$this->t_body->set_var("COLOUR", "#FF0000");
						else
							$this->t_body->set_var("COLOUR", "#000000");
		
						$this->t_body->set_var("ASSDESC", $result['description']);
						if($result['realname'] == "")
							$this->t_body->set_var("VIEW", "");		
						else
						{
							if($allowedFileTypes[$result['mime_type']][1] == 1)
								$url = "<a href=\"/sendframe.php?id=".$result['fid']."\" target=\"_blank\">View</a>";
							else
								$url = "<a href=\"/send.php?id=".$result['fid']."&save=1\">View</a>";
							$this->t_body->set_var("VIEW", $url);
						}
						$this->t_body->set_var("DUEDATE", $result['date']);
						$this->t_body->set_var("SUBMIT", "<a href=\"submitWork.php?box_id=".$result['id']."\">Submit</a>");
					
						$this->t_body->parse("due_list","due_block",true);

					
					}
					else
						$completed++;
						
					$catName = $result['name'];
				}


			        //if all due work not completed set havework flag and parse subject block
                                if($due != $completed)
				{
                                       $havework = 1;
					$this->t_body->set_var("CATEGORY", $catName);
					$this->t_body->parse("category_listing", "category_block", true);
					$this->t_body->set_var("due_list", "");
				}
	

			}
			
		}
		if(!$havework)	
			$this->t_body->set_var("category_listing", "<b>You have completed all work at this time</b>");
		
	}		

	function logPageView()
	{
		$testquery = "SELECT datetime FROM submission_log WHERE page = '1' 
		AND user = '".$_SESSION['id']."'";
		
		$this->db->dbDoQuery($testquery);

		if($row = $this->db->dbGetNextArray() )
		{
			$query = "UPDATE submission_log SET datetime = NOW() WHERE page = 
			'1' AND user = '".$_SESSION['id']."'";
		}
		else
		{
			$query = "INSERT INTO submission_log (datetime, user, page) VALUES ( NOW() ,
				'".$_SESSION['id']."', '1')";
		}
                
		$this->db->dbDoQuery($query);
	}

	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
}



$page = new dueWorkPage();
$page->getDueWork();
$page->showPage();
