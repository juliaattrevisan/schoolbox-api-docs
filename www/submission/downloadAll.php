<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

SitePage::extendExecutionTime(300);

$page = new SitePage;

if(is_numeric($_GET['id'])) {
    $box_id = $_GET['id'];

    // check that we have read access to the submissions directory
    if(!is_readable(Cfg::get('submission_dir') . '/')) {
        error(9, "the requested submissions could not be read.<br />Please try again, and if the problem persists contact your system administrator.");
    }

    $subBoxHandler = new Submission();

    $submissionBox = $subBoxHandler->getByID($box_id);
    $zipName = Scrub::text($submissionBox['name']);

    $group = new Group();
    if ($group->getPermission($submissionBox['category']) >= WRITE_ACCESS) {
        $submissionList = $subBoxHandler->getAllForBox($box_id);

        $zip = new SubmissionZipFolder($zipName, $submissionList);
        $zip->sendZip();
    }
    else {
        error(5);
    }
}
