<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';

$this->setFile(TEMPLATE_content, 'submission/returnWork.html');
$this->setTitle('File Submission');

$subHandler = new Submission;
$catHandler = new Category;
$userHandler = new User;

if(is_numeric($_GET['box_id']) && is_numeric($_GET['sid']) ) {

    $subbox = $subHandler->getByID($_GET['box_id']);
    $category = $catHandler->getByID($subbox['category']);
    $user = $userHandler->getByID($_GET['sid']);

    $output = array("CATEGORY"  => scrubTEXT($category['name']),
                    "ASSNAME"   => scrubTEXT($subbox['description']),
                    "BOX_ID"    => $_GET['box_id'],
                    'NAME'      => formatName($user),
                    'SID'       => $_GET['sid'],
                );

    $this->setVar($output);

}



/*
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
		$this->db->query($query);

		if($this->db->countRows() < 1)
		{
			error(25);
		}
	
		//check work exist and it isnt already marked
		$query = "SELECT marked FROM student_submission WHERE box_id = '$box_id' AND owner = '$sid' AND marked = '0'";
		$this->db->query($query);

		if($this->db->countRows() < 1)
		{
			error(26);
		}
			
	}

	function workMarked($box_id, $sid)
	{
	
		$query = "UPDATE student_submission SET marked = '1' WHERE box_id = '$box_id' AND owner = '$sid'";
		$this->db->query($query);
	} 

	function returnWork($sid, $box_id, $comment, $mark)
	{
		//insert comment and mark
		$query = "INSERT INTO submission_return (box_id, comment, mark, owner, returned, deleted) 
				VALUES ('$box_id', '$comment', '$mark', '$sid', NOW(), '0' )";
		$this->db->query($query);	
	}

	function returnWorkWithFile($file, $file_name, $sid, $box_id, $comment, $mark)
	{
        	//INSERT comment, mark and file 
        	$query = "INSERT INTO submission_return (box_id, comment, mark, owner, filename) 
		VALUES ('$box_id','$comment','$mark', '$sid', '$file_name')";

        	$this->db->query($query);

        	//get file ID from INSERT
        	$fileId = $this->db->dbInsertId();

        	//save file using file ID as name
        	$newFileName = $fileId . ".dat";
        	move_uploaded_file($file, RETURNS_directory . "/" . $newFileName);
	}

	
        function showPage($box_id, $sid)
        {

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
		fileSecurityCheck($_FILES['file']['error'], $_FILES['file']['name'], $_FILES['file']['tmp_name'], $_FILES['file']['size'], $_FILES['file']['type']);

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
 *
 */

?>
