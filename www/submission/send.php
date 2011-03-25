<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//this script opens files for download and send them to the user with correct filename
//future enhancements could include storing the content type to help browser file recognition
//rather than just octet stream

//includes
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';

if(is_numeric($_GET['id']))
{
	$page = new SitePage;

	if(is_numeric($_GET['return'])) {
		require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionReturn.php';
		$submissionReturn = new SubmissionReturn;

		$return = $submissionReturn->getByID($_GET['id']);

		if(!isset($return['id']))
			error(44);

		if($return['owner'] != $_SESSION['id'])
			error(44);

		$file = Cfg::get('returns_dir') . '/' . $return['id']. '.dat';
		$filename = $return['filename'];
	}
	else {
		require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionStudent.php';
		require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
		$submissionStudent = new SubmissionStudent;
		$submissionHandler = new Submission;
		$studentSub = $submissionStudent->getByID($_GET['id']);


		if (!isset($studentSub['id']))
            error(44);

		$subBox = $submissionHandler->getByID($studentSub['box_id']);

		/* Allow students to view their own submitted work
		 * Allow submission box owners to view submissions
		 */
		if (($studentSub['owner'] != $_SESSION['id']) && ($subBox['owner'] != $_SESSION['id'])) {
			error(44);
		}

		$file = Cfg::get('submission_dir') . '/' . $studentSub['id'] . '.dat';
		$filename = $studentSub['lastname']."-".$studentSub['realname'];
	}

	if($_GET['view'])
		$attach = "";
	else
		$attach = "attachment;";

	//send headers
	header ("Content-Type: application/octet-stream");
	header ("Content-Disposition: $attach filename=\"".$filename."\"");
	header ("Content-Length: ".filesize($file));
	readfile($file);
}
else
	error(2);
?>
