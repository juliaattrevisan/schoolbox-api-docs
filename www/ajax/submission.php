<?php

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

use Schoolbox\Assessment\Component\SubmissionBoxMapper;

$page = new SitePage;

if ($_REQUEST['id']) {

    $subStudHandler = new SubmissionStudent;
    $groupHandler = new Group;

    $submission_mapper = new SubmissionBoxMapper();
    $submission        = $submission_mapper->findById($_REQUEST['id']);
    $submission_id     = $submission->getId();
    $submission_cat_id = $submission->getCategory();


    /* Check involved in subject */
    $groupHandler = new Group;

    if (!$groupHandler->involvedInSubject($submission_cat_id) && !$groupHandler->involvedInGroup($_SESSION['id'], $submission_cat_id)) {
        exit();
    }

    $current_time = new DateTime();

    /* Get status to set action */
    $student_submission = $subStudHandler->getStatus($_SESSION['id'], $submission_id);

    if ($student_submission['accept_submission']) {
        $action = 'View Details';
    } elseif ($student_submission['status'] == 1 && $current_time > $student_submission['open_date'] && $current_time < $student_submission['late_date']) {
        $action = 'Submit Work';
    } else {
        $action = 'View Details';
    }

    //
    // Dump a list of files.
    $files = array();
    foreach ($submission->getFileLinks() as $file_link) {
        $file = $file_link->getFile();
        $files[] = array(
            'hash'  => $file->getHash(),
            'name'  => $file->getFilename(),
            'thumb' => $file->getIconFromFilename(),
            'size'  => Alaress_Byte_Format::bytesToString($file->getFilesize()),
            'date'  => date('jS M Y, g:ia', strtotime($file->getUploadedOn()))
        );
    }

    $output = array(
        'id'          => $submission_id,
        'cid'         => $submission_cat_id,
        'category'    => Scrub::text($submission->getCategoryName()),
        'files'       => $files,
        'legacy_fid'  => $submission->getLegacyFileId(),
        'typeName'    => Scrub::text($submission->getWorkTypeName()),
        'name'        => Scrub::text($submission->getName()),
        'description' => Scrub::text($submission->getDescription()),
        'comment'     => Scrub::text($submission->getLegacyFileComment()),
        'action'      => $action,
    );

}

header("Content-type: application/json");
print json_encode($output);
