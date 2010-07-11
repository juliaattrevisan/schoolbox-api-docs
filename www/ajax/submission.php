<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once SITE_root . '/library/submission.php';
require_once SITE_root . '/library/submissionStudent.php';
require_once SITE_root . '/library/group.php';

$page = new SitePage;

if($_REQUEST['id']) {

	$submissionHandler = new Submission;
	$subStudHandler = new SubmissionStudent;
	$groupHandler = new Group;

	$subBox = $submissionHandler->getByID($_REQUEST['id']);

	/* Check involved in subject */
	$groupHandler = new Group;

	if(!$groupHandler->involvedInSubject($subBox['cid']) && !$groupHandler->involvedInGroup($_SESSION['id'], $subBox['cid']))
		exit();

	$output = array('description'	=> scrubTEXT($subBox['description']),
					'category'		=> scrubTEXT($subBox['categoryName']),
					'comment'		=> scrubTEXT($subBox['comment']),
					'fid'			=> $subBox['fid'],
					'cid'			=> $subBox['cid'],
					'type'			=> scrubTEXT($subBox['typeName']),
					'id'			=> $subBox['id'],
					'submitted'		=> $subStudHandler->checkSubmitted($subBox['id'], $_SESSION['id'])?'true':'false',
        );
}

header("Content-type: application/json");
print json_encode($output);


?>
