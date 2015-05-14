<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

/* Start a session -_-
 */
$page = new SitePage();

/* Get the owner object
 */
$userMapper = new \Schoolbox\UserMapper();
$user = $userMapper->findById($_POST['uid']);

/* Get the submission box object
 */
$submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
$submissionBox = $submissionBoxMapper->findById($_POST['box_id']);

// Security check
$studentRetHandler = new SubmissionReturn();
$studentRetHandler->securityCheck($submissionBox->getId());
unset($studentRetHandler);

/* Some weird format that's built in javascript for some reason
 * See jquery.marksheet.js
 */
$mark = $_POST['mark'][$_POST['uid']];

/* Optional rubric
 */
$rubric = $_POST['rubric'];

/* The usual comment
 */
$comment = $_POST['comment'];

/* Optional file attachment
 * If there is none - save this work without an attachment!
 * Clear attachment only if a particular field is set?
 */
$attachments = isset($_POST['attachment']) ? $_POST['attachment'] : array();

// Are attachments cleared if none are provided?
$removeUnsetAttachments = !empty($_POST['clearAttachments']);

/* Crocodoc uuid, to view return independently of submission
 */
if (isset($_POST['crocodoc'])) {
    $crocodoc = $_POST['crocodoc']['uuid'];
} else {
    $crocodoc = null;
}

/* A url to visit after submitting
 */
$redirect = $_POST['redirect'];

/* Process the return's comments, marks, rubrics and uploads
 */
$returnedWorkFactory = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\Factory(
    $user,
    $submissionBox,
    $mark,
    $comment,
    $rubric,
    $attachments,
    $removeUnsetAttachments,
    $crocodoc
);
$returnedWork = $returnedWorkFactory->start();

/* Go to St. Albans when finished
 */
if ($redirect) {
    $page->localRedir($redirect);
} else {
    echo "data stored";
}