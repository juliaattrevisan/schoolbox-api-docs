<?php

require_once("../include/session.php");
require_once("../include/include.php");


class returnWorkPage {

	function returnWorkPage()
	{

		$this->db = new dbManager;
		$this->db->dbConnect();

	}

	function securityCheck($box_id, $sid)
	{
		//check if person returning work is actual owner of the submission box
		
		$query = "SELECT owner FROM submission_box WHERE id = '$box_id' AND owner = '".$_SESSION['id']."'";
		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1)
		{
			error(25);
		}
	
		//check work exist and it isnt already marked
		$query = "SELECT marked FROM student_submission WHERE box_id = '$box_id' AND owner = '$sid' AND marked = '0'";
		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() < 1)
		{
			error(26);
		}
			
	}

	function workMarked($box_id, $sid)
	{
	
		$query = "UPDATE student_submission SET marked = '1' WHERE box_id = '$box_id' AND owner = '$sid'";
		$this->db->dbDoQuery($query);
	} 

	function returnWork($sid, $box_id, $comment, $mark)
	{
		//insert comment and mark
		$query = "INSERT INTO submission_return (box_id, comment, mark, owner, returned, deleted) 
				VALUES ('$box_id', '$comment', '$mark', '$sid', NOW(), '0' )";
		$this->db->dbDoQuery($query);	
	}

	function returnWorkWithFile($file, $file_name, $sid, $box_id, $comment, $mark)
	{
        	//INSERT comment, mark and file 
        	$query = "INSERT INTO submission_return (box_id, comment, mark, owner, filename) 
		VALUES ('$box_id','$comment','$mark', '$sid', '$file_name')";

        	$this->db->dbDoQuery($query);

        	//get file ID from INSERT
        	$fileId = $this->db->dbInsertId();

        	//save file using file ID as name
        	$newFileName = $fileId . ".dat";
        	move_uploaded_file($file, RETURNS_directory . "/" . $newFileName);
	}

	
        function showPage($box_id, $sid)
        {
                $this->t_body = new Template(TPL_directory);
               	$this->t_body->set_file("tplBODY", "submission/returnWork.htm");
		$this->t_body->set_var("TITLE", HEADING." - File Submission");
		
		include_once("../include/page.php");

		//get details about the work being returned for user verification
		$query = "SELECT category.name, file.description FROM category, file, submission_box WHERE 
		submission_box.category = category.id AND submission_box.fid = file.id 
		AND submission_box.id = $box_id";

		$this->db->dbDoQuery($query);
		$result = $this->db->dbGetNextArray();
		
		$this->t_body->set_var("CATEGORY",$result['name']);
		$this->t_body->set_var("ASSNAME", $result['description']);
		$this->t_body->set_var("BOX_ID", $box_id);
		
		//get details of user work being returned to for user verification
		$query = "SELECT lastname, title, firstname FROM user WHERE id = '$sid'";
		$this->db->dbDoQuery($query);
		$result = $this->db->dbGetNextArray();
		
		$this->t_body->set_var("NAME", formatName(NULL, $result));
		$this->t_body->set_var("SID", $sid);
                $this->t_body->pparse("finaloutput", "tplBODY");
        }
}

//create the object

$myPage = new returnWorkPage();

if( ((isset($_POST['comment']) && $_POST['comment']) || (isset($_POST['mark']) && $_POST['mark']) || (isset($_FILES['file']['name']) && 
	$_FILES['file']['name'])) && (isset($_POST['sid']) && $_POST['sid']) && (isset($_POST['box_id']) && $_POST['box_id']))
{
	$myPage->securityCheck($_POST['box_id'], $_POST['sid']);
	
	if( isset($_FILES['file']['name']) && $_FILES['file']['name'] )
	{
		require("../include/fileSecurity.php");
		fileSecurityCheck(true, true, true, true);

		//all checks passed add it
		$myPage->returnWorkWithFile($_FILES['file']['tmp_name'], $_FILES['file']['name'], $_POST['sid'], $_POST['box_id'], 
			$_POST['comment'], $_POST['mark']);
	}
        else
	{	
		//else file not submitted so just submit other stuff
       		$myPage->returnWork($_POST['sid'], $_POST['box_id'], $_POST['comment'], $_POST['mark']);

	}	

	//update submission to marked
	$myPage->workMarked($_POST['box_id'], $_POST['sid']);

	//output code to send the browser to where it came from
        $back = -2;
        include("../include/back.php");
	exit;
}
else if((isset($_GET['box_id']) && $_GET['box_id']) && (isset($_GET['sid']) && $_GET['sid']))
	$myPage->showPage($_GET['box_id'], $_GET['sid']);
else
{
	error(17);
}

?>
