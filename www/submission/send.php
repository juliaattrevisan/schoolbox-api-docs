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
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';

if(is_numeric($_GET['id']))
{
	$page = new SitePage;

	if(is_numeric($_GET['return'])) {
		require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionReturn.php';
		$submissionReturn = new SubmissionReturn;

        $groupHandler = new Group;

		$return = $submissionReturn->getByID($_GET['id']);

		if(!isset($return['id']))
			error(44);

        // Allow file return owner, parent of owner or all staff access to this file
		if( !( $return['owner'] == $_SESSION['id'] || $groupHandler->isParentOfChild($return['owner']) || $groupHandler->isStaff()  ) )
			error(44);

		$file = Cfg::get('returns_dir') . '/' . $return['id']. '.dat';

        // does this file exist, and is it readable?
        if(!is_readable($file)) {
            error(9, "the requested file could not be read.<br />Please try again, and if the problem persists contact your system administrator.");
        }

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

        // does this file exist, and is it readable?
        if(!is_readable($file)) {
            error(9, "the requested file could not be read.<br />Please try again, and if the problem persists contact your system administrator.");
        }

        // filename format to be {lastname-firstname_month-day_hour-minute_{sanitised(filename)}}
        // eg. Day-Matt_0816-1432_MyAssignment.docx (from 'My Assign!ment.docx')

        $filename = sprintf("%s-%s_%s_%s",
            $studentSub['lastname'], $studentSub['firstname'],  // student name
            date("md-Hi", strtotime($studentSub['submitted'])), // datetime
            $studentSub['realname']                             // filename
        );

	}

    // sanitise
    $filename = preg_replace("#[^a-z0-9_\-\(\)\.]#i", "", $filename);

    // get the mime-type
    $allowedHandler = new AllowedFile();
    $mime = $allowedHandler->getMime(substr($filename, strripos($filename, '.') + 1));

    $attach = $_GET['view'] ? '' : 'attachment;';

	//send headers (cache-control off stops IE8 from failing to download)
	header ("Pragma: ");
	header ("Cache-control: ");
	header ("Content-Type: $mime");
	header ("Content-Disposition: $attach filename=\"".$filename."\"");
	header ("Content-Length: ".filesize($file));
	readfile($file);

    die(); // just in case
}
else
	error(2);
?>
