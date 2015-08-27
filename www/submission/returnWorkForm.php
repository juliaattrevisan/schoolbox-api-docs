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

if (is_numeric($_GET['delete'])) {
    // Delete a previously returned work
    $mapper = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper();
    try {
        $return = $mapper->findById($_GET['delete']);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(44);
        exit;
    }

    // Security check
    $boxId  = $return->getSubmissionBox()->getId();
    $userId = $return->getOwner()->getId();
    $studentRetHandler = new SubmissionReturn();
    $studentRetHandler->securityCheck($return->getSubmissionBox()->getId());
    unset($studentRetHandler);

    // Delete and redir somewhere
    $mapper->delete($return);
    SitePage::localRedir("/submission/inlineGrading/view/{$boxId}/{$userId}");
    exit;
}

// Resume normal service

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
// If the mark gets passed at the same time as rubrics,
// then rubrics get clobbered. We don't want that.
if ($rubric) {
    $mark = null;
}

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
$crocodoc = null;
if (isset($_POST['crocodoc'])) {
    try {
        $submissionMapper = new \Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper;
        $studentSubmission = $submissionMapper->findById($_POST['crocodoc']);
        $crocodoc = $studentSubmission->getUuid();
    } catch(\Schoolbox\Mapper\Exception $e) {
        //weird, no submission found, but we can continue
    }
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
