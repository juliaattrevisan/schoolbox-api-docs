<?php

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage;

if ($_REQUEST['id']) {

	$submissionHandler = new Submission;
	$subStudHandler = new SubmissionStudent;
	$groupHandler = new Group;

	$subBox = $submissionHandler->getByID($_REQUEST['id']);

	/* Check involved in subject */
	$groupHandler = new Group;

	if (!$groupHandler->involvedInSubject($subBox['cid']) && !$groupHandler->involvedInGroup($_SESSION['id'], $subBox['cid'])) {
        exit();
    }

    $current_time = new DateTime();

    /* Get status to set action */
    $student_submission = $subStudHandler->getStatus($_SESSION['id'], $subBox['id']);

    if ($student_submission['accept_submission']) {
        $action = 'View Details';
    } elseif ($student_submission['status'] == 1 && $current_time > $student_submission['open_date'] && $current_time < $student_submission['late_date']) {
        $action = 'Submit Work';
    } else {
        $action = 'View Details';
    }

	$output = array(
        'id'			=> $subBox['id'],
        'cid'			=> $subBox['cid'],
        'category'		=> Scrub::text($subBox['categoryName']),
        'fid'			=> $subBox['fid'],
        'typeName'		=> Scrub::text($subBox['typeName']),
        'name'	        => Scrub::text($subBox['name']),
        'description'	=> Scrub::text($subBox['description']),
        'comment'		=> Scrub::text($subBox['comment']),
        'action'		=> $action,
    );

}

header("Content-type: application/json");
print json_encode($output);


?>
