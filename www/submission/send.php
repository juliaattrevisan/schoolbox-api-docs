<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//this script opens files for download and send them to the user with correct filename
//future enhancements could include storing the content type to help browser file recognition
//rather than just octet stream

use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;

//includes
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';

if (is_numeric($_GET['id'])) {
	$page = new SitePage;

	//
	// File contents, size and mime type
	$contents = null;
	$size     = null;
	$mime     = null;
	$attach   = $_GET['save'] ? 'attachment;' : '';
	if(is_numeric($_GET['return'])) {
		$mapper = new ReturnedWorkMapper();
		try {
			$return = $mapper->findById($_GET['id']);
		}
		catch (Schoolbox\Mapper\Exception $e) {
			error(44);
			exit;
		}

		// Allow file return owner, parent of owner or all staff access to this file
		$groupHandler = new Group;
		$owner        = $return->getOwner()->getId();
		if (!($owner == $_SESSION['id']
			|| $groupHandler->isParentOfChild($owner)
			|| $groupHandler->isStaff()
		)) {
			error(44);
		}

		$file = $return->getFileLocation();

		// does this file exist, and is it readable?
		if (!is_readable($file)) {
			error(9, "the requested file could not be read.<br />Please try again, and if the problem persists contact your system administrator.");
		}

		$contents = file_get_contents($file);
		$size     = $return->getFilesize();
		$filename = $return->getFilename();
	}
	else {
		$mapper = new SubmissionMapper();
		try {
			$studentSub = $mapper->findById($_GET['id']);
		}
		catch (Schoolbox\Mapper\Exception $e) {
			error(44);
			exit;
		}

		$subBox = $studentSub->getSubmissionBox();

		/* Allow students to view their own submitted work
		 * Allow submission box owners to view submissions
		 */
		if (($studentSub->getOwner()->getId() != $_SESSION['id'])
			&& ($subBox->getOwner()->getId() != $_SESSION['id'])
		) {
			error(44);
		}

		// filename format to be {lastname-firstname_month-day_hour-minute_{sanitised(filename)}}
		// eg. Day-Matt_0816-1432_MyAssignment.docx (from 'My Assign!ment.docx')
		$filename = sprintf("%s-%s_%s_%s",
			$studentSub->getOwner()->getLastName(),
			$studentSub->getOwner()->getFirstName(),
			$studentSub->getSubmitted()->getUsingFormat("md-Hi"),
			$studentSub->getFilename()
		);

		//
		// Load the file (whether an upload or an online submission)
		$file = $studentSub->getFileLocation();
		$name = $studentSub->getFilename();
		$mime = $studentSub->getMimetype()->getMime();
		if (is_readable($file)) {
			$contents = file_get_contents($file);
			$size     = $studentSub->getFilesize();
		}
		else {
			//
			// Always show online submissions as inline - don't offer for
			// download, because they may include uploaded images.
			$attach   = '';
			$contents = <<<HTML
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>%s</title>
</head>
<body>%s</body>
</html>
HTML
			;
			$contents = sprintf($contents, htmlspecialchars($filename), Scrub::html($studentSub->getBody()));
			$size     = strlen($contents);
			if ($size === 0) {
				error(9, "the requested file could not be read.<br />Please try again, and if the problem persists contact your system administrator.");
			}
		}
	}

	// sanitise
	$filename = preg_replace("#[^a-z0-9_\\-\\(\\)\\.]#i", "", $filename);

	// get the mime-type (if not set already)
	if (!isset($mime)) {
		$allowedHandler = new AllowedFile();
		$mime = $allowedHandler->getMime(substr($filename, strripos($filename, '.') + 1));
	}

	//send headers (cache-control off stops IE8 from failing to download)
	header ("Pragma: ");
	header ("Cache-control: ");
	header ("Content-Type: $mime");
	header ("Content-Disposition: $attach filename=\"".$filename."\"");
	header ("Content-Length: ".$size);
	echo $contents;
	die(); // just in case
}
else {
	error(2);
}
