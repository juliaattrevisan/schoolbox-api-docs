<?php
/************************************************************
*                                                           *
*         Copyright (c) 2013 Alaress Pty Ltd                *
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
$groupHandler = new Group;

if (is_numeric($_GET['id'])) {
	$page = new SitePage;

	//
	// File name and/or contents, size and mime type
	$file     = null;
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

		$size     = $return->getFilesize();
		$filename = $return->getFilename();
	}
	else {
		$mapper = new SubmissionMapper();
		try {
			$studentSub = $mapper->findById($_GET['id']);
			// Now lazy model is gone, need to load submission box and owner manually
			$studentSub->setSubmissionBox(
				SubmissionBoxMapper::getInstance()->findById(
					$studentSub->getSubmissionBox()->getId()
				)
			);
			$studentSub->setOwner(
				Schoolbox\UserMapper::getInstance()->findById(
					$studentSub->getOwner()->getId()
				)
			);
		}
		catch (Schoolbox\Mapper\Exception $e) {
			error(44);
			exit;
		}

		$subBox = $studentSub->getSubmissionBox();


        // Check if file has been published/showcased in the ePortfolio, if so anyone can download the file
        $published = false;

        try {
            $showcase = Eportfolio\ShowcaseMapper::getInstance()->findOneByColumn('submission_box_id',$studentSub->getSubmissionBox()->getId());
            $published = true;
        }
        catch (Schoolbox\Mapper\Exception $e) {$published = false;
        }

        //Check if staff has permission
        $permission = $groupHandler->getPermission($subBox->getCategory()->getId());


		/* Allow students to view their own submitted work
		* Allow staff members with write permission to this folder access
		* Allow work that has been showcased to be viewed
		*/
		if (($studentSub->getOwner()->getId() != $_SESSION['id'])
			&& $permission < WRITE_ACCESS
            && !$published 
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
			$size = $studentSub->getFilesize();
		}
		else {
			//
			// Always show online submissions as inline - don't offer for
			// download, because they may include uploaded images.
			$attach   = '';
			$wrapper  = new Alaress\Html\Wrapper($filename, $studentSub->getBody());
			$wrapper->loadBaseHrefFromServerVars($_SERVER);
			$contents = $wrapper->render();
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
	if (isset($contents)) {
        header ("Content-Length: ".$size);
        echo $contents;
    }
    elseif (is_readable($file)) {

		/* Scrub it, if option is specified in the querystring
		 */
		if ($_GET['scrub'] == 1) {
			$contents = Scrub::html(file_get_contents($file));
			$size = strlen($contents);

			header ("Content-Length: ".$size);
			echo $contents;
			exit();
		}
		else {
			if (empty($size)) {
				$size = filesize($file);
			}

			header ("Content-Length: ".$size);
			readfile($file);
		}
    }

	die(); // just in case
}
else {
	error(2);
}
