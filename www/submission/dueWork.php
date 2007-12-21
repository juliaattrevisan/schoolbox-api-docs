<?php

require_once('../include/session.php');
require_once('../include/include.php');
require_once('../include/involved.php');
require_once('../include/syn_userinfo.php');

class dueWorkPage 
{
	function dueWorkPage()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "submission/dueWork.htm");
		$this->t_body->set_var("TITLE", HEADING." - Due Work");
		
		//due work
		$this->t_body->set_block("tplBODY", "user_block", "user_list");
		$this->t_body->set_block("user_block", "user_heading", "user_heading_area");
		$this->t_body->set_block("user_block", "due_category_block", "due_category_listing");
		$this->t_body->set_block("due_category_block", "due_block", "due_list"); 
	
		//returned work
		$this->t_body->set_block("tplBODY", "returned_block", "returned_list");
		$this->t_body->set_block("returned_block", "ret_category_block", "ret_category_listing");
		$this->t_body->set_block("ret_category_block", "return_block", "return_list"); 
		
		include_once("../include/page.php");

		$this->db = new dbManager;

		$this->logPageView();
	}

	function displayDueWork($id = 0)
	{
		if(isParent($_SESSION['gid']))
		{
			//if parent output childs work
			foreach($_SESSION['children'] as $child)
			{
				$flag=false;
				$query = "SELECT title, firstname, lastname, year FROM user WHERE id = {$child['id']}";
				$user = $this->db->dbGetSingleRow($query);
				$this->t_body->set_var("USER_NAME", formatName($user));
				if($user['year'])
					$this->t_body->set_var("USER_YEAR", ', '.$user['year']);
				else
					$this->t_body->set_var("USER_YEAR", '');
				
				$this->t_body->parse("user_heading_area","user_heading");
				
				$this->outputCategories($child['id'], $child['subjects']);
				
			}
		}
		else if(is_numeric($id) && $id)
		{
			//if requested user check they are staff
			if(isStaff($_SESSION['gid']))
			{
				$query = "SELECT title, firstname, lastname, year, synergy_id, group_id FROM user WHERE id = $id";
				$user = $this->db->dbGetSingleRow($query);
				$this->t_body->set_var("USER_NAME", formatName($user));
				if($user['year'])
					$this->t_body->set_var("USER_YEAR", ', '.$user['year']);
				else
					$this->t_body->set_var("USER_YEAR", '');
				
				$this->t_body->parse("user_heading_area","user_heading");
				$subjects = getSubjectList($user['synergy_id'], $user['group_id']);
				$this->outputCategories($id, $subjects);		
			}
			else
				error(19); //perm denied
		}
		else
		{
		
			$this->t_body->set_var('user_heading_area', '');
			//just output own due work
			$this->outputCategories($_SESSION['id'], $_SESSION['subjects']);
		}
	}

	
	function outputCategories($id, $subjects)
	{
			$catList = involvedCategoryList($id, $subjects, 1);
			if(is_array($catList)) {
				foreach($catList as $cat)
				{
					if($this->outputCategoryWork($id, $cat['id']))
						$flag=1;
				}
			}
			if(!$flag)
				$this->t_body->set_var("due_category_listing", "<b>You have completed all work set via the portal</b>");
					
			$this->t_body->parse("user_list","user_block",true);
	}
	
	
	function outputCategoryWork($id, $catId)
	{
		$query = "
			SELECT file.description, file.realname, file.id AS fid, file_type.name as type,
				DATE_FORMAT(submission_box.date, '%a, %D of %b %l:%i%p') AS date, 
				UNIX_TIMESTAMP(submission_box.date) AS overdue_date,
				UNIX_TIMESTAMP(submission_box.open_date) AS open_date, 
				submission_box.id, category.name, student_submission.owner, inline,
				user.title, user.firstname, user.lastname
			FROM (file, submission_box, category) 
				LEFT JOIN user ON category.teacherid = user.id
				LEFT JOIN student_submission ON student_submission.box_id = submission_box.id AND student_submission.owner = $id
				LEFT JOIN allowedFileTypes ON file.mime_type = allowedFileTypes.mime
			 	LEFT JOIN file_type ON submission_box.type = file_type.id
			WHERE submission_box.fid = file.id AND submission_box.category = category.id 
				AND submission_box.deleted = '0' AND category.id = '$catId' 
				AND submission_box.late_date > NOW()
			ORDER BY submission_box.date";

		$res = $this->db->dbDoQuery($query);

		$now = date("U");
		
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
	
					$this->t_body->set_var("ASSDESC", scrubTEXT($result['description']));
					$this->t_body->set_var("TYPE", scrubTEXT($result['type']));
					if($result['realname'] == "")
						$this->t_body->set_var("VIEW", "");		
					else
					{
						if($result['inline'] == 'true')
							$url = "<a href=\"/sendframe.php?id=".$result['fid']."\" target=\"_blank\">View</a>";
						else
							$url = "<a href=\"/send.php?id=".$result['fid']."&save=1\">View</a>";
						$this->t_body->set_var("VIEW", $url);
					}
					$this->t_body->set_var("DUEDATE", $result['date']);

					if($_SESSION['id'] == $id && $result['open_date'] < date('U') )
						$this->t_body->set_var("SUBMIT", "<a href=\"submitWork.php?box_id=".$result['id']."\">Submit</a>");
					else
						$this->t_body->set_var("SUBMIT", "");
				
					$this->t_body->parse("due_list","due_block",true);

				
				}
				else
					$completed++;
					
				$catName = $result['name'];
				$teacherName = trim($result['title']." ".$result['firstname']." ".$result['lastname']);
				if (!empty($teacherName)) {
					$teacherName = " - ".$teacherName;
				}
			}


			//if all due work not completed set havework flag and parse subject block
			if($due != $completed)
			{
				$havework = 1;
				$this->t_body->set_var("CATEGORY", $catName);
				$this->t_body->set_var("TEACHER", $teacherName);
				$this->t_body->parse("due_category_listing", "due_category_block", true);
				$this->t_body->set_var("due_list", "");
				return true;
			}
			
		}
		return false;
	}
		
	function getReturnedWork($id)
	{	
		//dont show returned work to people requesting to see this page.. only show returned work to themselves
		if(is_numeric($id) && $id > 0) {
			$this->t_body->set_var('returned_list', '');
			return;
		}
			 
		$query = "SELECT category.id as cid, category.name, file.description, submission_return.mark, 
				submission_return.comment, 
				submission_return.id, submission_return.filename, file_type.name as type 
				FROM submission_return, category, file, submission_box
				LEFT JOIN file_type ON submission_box.type = file_type.id
				WHERE submission_return.box_id = submission_box.id AND submission_box.category = category.id 
				AND submission_box.fid = file.id AND submission_return.deleted = '0' 
				AND submission_return.owner = '".$_SESSION['id']."'";

		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() > 0)
		{
			$return = $this->db->dbGetNextArray();
			$prevCat = $return;			
			do
			{

				if($prevCat['cid'] != $return['cid'])
				{
					$this->t_body->set_var("CATEGORY", $prevCat['name']);
 					$this->t_body->parse("ret_category_listing", "ret_category_block", true);
					$this->t_body->set_var("return_list", "");
				}


				$this->t_body->set_var("ASSDESC", $return['description']);
				$this->t_body->set_var("TYPE", $return['type']);
				$this->t_body->set_var("MARK", $return['mark']);
				$this->t_body->set_var("COMMENT", $return['comment']);
				
				$this->t_body->set_var("DELETE", "dueWork.php?delete=".$return['id']);
				if($return['filename'] == "")
					$this->t_body->set_var("VIEW", "");
				else
					$this->t_body->set_var("VIEW", "<a href=\"dueWork.php?view={$return['id']}\">View</a>");
						
				$this->t_body->parse("return_list","return_block",true);
				
				$prevCat = $return;
			}while($return = $this->db->dbGetNextArray());


		$this->t_body->set_var("CATEGORY", $prevCat['name']);
		$this->t_body->parse("ret_category_listing", "ret_category_block", true);

		}
		else
			$this->t_body->set_var("ret_category_listing", "<b>You currently have no returned work</b>");
		
		$this->t_body->parse('returned_list', 'returned_block');
	}		

	function deleteReturnedWork($id)
	{
		//mark work deleted but only when the work is owned by user
		$query = "UPDATE submission_return SET deleted = '1' WHERE id = '$id' AND owner = '".$_SESSION['id']."'";
		$this->db->dbDoQuery($query);
	}

	function viewReturnedWork($id, $save)
	{
		//sends students work to student only
		$query = "SELECT filename FROM submission_return WHERE owner = '".$_SESSION['id']."' AND id = '$id'";
		$this->db->dbDoQuery($query);
		
		if($this->db->dbCountRows() < 1) 
			error(5);
        
		$result = $this->db->dbGetNextArray();		
		if($save)
			$attach = "attachment;";
		else
			$attach = "";

		//all checks completed.. continue with file send
		$filename = RETURNS_directory . "/" . $id . ".dat";
		
		//send headers
		header ("Content-Type: application/octet-stream"); 
		header ("Content-Disposition: $attach filename=\"".$result['filename']."\""); 
		header ("Content-Length: ".filesize($filename));
		readfile($filename);
		exit;
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

//if user requests to view send them the file
if(isset($_REQUEST['view']) && $_REQUEST['view'])
	$page->viewReturnedWork($_REQUEST['view'], $_REQUEST['save']);
//mark work deleted and then display
else if(isset($_REQUEST['delete']) && $_REQUEST['delete']) {
	$page->deleteReturnedWork($_REQUEST['delete']);
	$page->showPage();
}

$page->displayDueWork($_GET['id']);
$page->getReturnedWork($_GET['id']);
$page->showPage();
