<?php

require_once("../include/session.php");
require_once("../include/include.php");


class submitWorkPage {
	function submitWorkPage()
	{

		$this->db = new dbManager;
		$this->db->dbConnect();
	}

	function securityCheck($box_id)
	{
		
		//check if already submitted
		$query = "SELECT id FROM student_submission WHERE box_id = '$box_id' AND owner = '".$_SESSION['id']."'";
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0)
		{
			error(27);
		}
			
		//check if submitted assignment is late
		
		$query = "SELECT UNIX_TIMESTAMP(date) AS overdue, code, category.id FROM submission_box, category 
		WHERE submission_box.id = '$box_id' AND submission_box.category = category.id";
		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1)
		{
			error(17);
		}

		$result = $this->db->dbGetNextArray();

		require_once("../include/involved.php");
		if(!involvedInSubject($result['code']) && !involvedInGroup($result['id']))
		{
			error(24);
		}
		
		//check if it is late
		$now = date("U");
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

        	$this->db->dbDoQuery($query);

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

		$query = "SELECT category.name, file.description FROM category, file, submission_box WHERE 
		submission_box.category = category.id AND submission_box.fid = file.id 
		AND submission_box.id = $box_id";

		$this->db->dbDoQuery($query);
		$result = $this->db->dbGetNextArray();
		
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
	require("../include/fileSecurity.php");
	fileSecurityCheck(true, true, true, true);
        	
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
