<?php

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/involved.php");
require_once("../include/fileSecurity.php");

class submitWorkPage {
	function submitWorkPage()
	{
		$this->db = new dbManager;
	}

	function securityCheck($box_id)
	{		
		//check if already submitted
		$query = "SELECT id FROM student_submission WHERE box_id = '$box_id' AND owner = '".$_SESSION['id']."'";
		if(is_array($this->db->getRow($query)))	
			error(27);
			
		//check if submitted assignment is late
		$query = "SELECT UNIX_TIMESTAMP(date) AS overdue, UNIX_TIMESTAMP(open_date) AS open, code, category.id FROM submission_box, category,

		WHERE submission_box.id = '$box_id' AND submission_box.category = category.id";
		$result = $this->db->getRow($query);
		if(!is_array($result))
			error(17);
		
		//check if they are involved in subject (dont allow anyone to submit to box) use involved in 
		if(!involvedInSubject($result['code']) && !involvedInGroup($result['id']))
			error(24);
		
		$now = date("U");
		
		//check if its early
		if($result['open'] > $now) 
			error(36);
		
		//check if it is late
		if($result['overdue'] < $now)
			return true;
		else
			return false;
	}


	function addFile($file_name, $box_id)
	{
		$late = $this->securityCheck($box_id);

		//INSERT file details
		$query = "INSERT INTO student_submission (owner, realname, submitted, box_id, late) VALUES 
					('".$_SESSION['id']."','$file_name', NOW(), $box_id, '$late')";
		
		$this->db->query($query);
		
		//get file ID from INSERT
		$fileId = $this->db->dbInsertId();
		
		//save file using file ID as name
		$newFileName = $fileId . ".dat";
		move_uploaded_file($_FILES['file']['tmp_name'], SUBMISSION_directory . "/" . $newFileName);
		
		return $fileId;
	
	}

	function showPage($box_id)
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "submission/submitFile.htm");
		$this->t_body->set_var("TITLE", HEADING." - Submit File");
		
		include_once("../include/page.php");
		
		$query = "SELECT category.name, submission_box.description, category.code, category.id 
				  FROM (category, submission_box)
				  WHERE submission_box.category = category.id 
					AND submission_box.id = $box_id";
		
		$result = $this->db->getRow($query);
		if(!is_array($result))
			error(2);
		
		//check involvement
		if(!involvedInSubject($result['id']) && !involvedInGroup($result['id']))
			error(24);
		
		$this->t_body->set_var("CATEGORY",$result['name']);
		$this->t_body->set_var("FILE", $result['description']);
		$this->t_body->set_var("BOX_ID", $box_id);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
}

//create the object

$myPage = new submitWorkPage();

if( isset($_FILES['file']['name']) && $_FILES['file']['name'] && $_REQUEST['box_id'])
{
	fileSecurityCheck($_FILES['file']['error'], $_FILES['file']['name'], $_FILES['file']['tmp_name'], $_FILES['file']['size'], $_FILES['file']['type']);
		
	//all checks passed add it
	$myPage->addFile($_FILES['file']['name'], $_REQUEST['box_id']);
	
	//output code to send the browser to where it came from
	$back = -2;
	include("../include/back.php");
	exit;
}
else if($_GET['box_id'])
	$myPage->showPage($_GET['box_id']);
else
{
	error(17);
}

?>
