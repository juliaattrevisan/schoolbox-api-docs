<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\DescriptorMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Rubric;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Rubric\Factory;
use Schoolbox\Collection;

require_once(dirname(__FILE__) . '/../../library/Bootstrap.php');

/* Start a session -_-
 */
$page = new SitePage();

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

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

    $submissionBoxMapper = \Schoolbox\Assessment\Component\SubmissionBoxMapper::getInstance();

    try {
        $submissionBox = $submissionBoxMapper->findById($boxId);
    }
    catch (\Schoolbox\Mapper\Exception $e) {
        error(17);
        exit();
    }

    if (!$submissionBox->hasAccess($sessionUser, \Schoolbox\Permission\Access\UserAccess::ACCESS_WRITE)) {
        error(25);
        exit();
    }

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
try {
    $submissionBoxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
    $submissionBox       = $submissionBoxMapper->findById($_POST['box_id']);
}
catch (\Schoolbox\Mapper\Exception $e) {
    error(17);
    exit();
}

// Security check
if (!$submissionBox->hasAccess($sessionUser, \Schoolbox\Permission\Access\UserAccess::ACCESS_WRITE)) {
    error(25);
    exit();
}

$term = Schoolbox\Assessment\Term\Mapper::getInstance()->findForDate($submissionBox->getDueDate());
if (!$term->isMarkableBy($_SESSION['user'])) {
    // ¯\_(ツ)_/¯
    error(17);
    exit;
}

/* Some weird format that's built in javascript for some reason
 * See jquery.marksheet.js
 */
$mark = $_POST['mark'][$_POST['uid']];

/* Optional rubric
 */
// If the mark gets passed at the same time as rubrics,
// then rubrics get clobbered. We don't want that.
if (is_array($_POST['rubric'])) {

    $desMap = DescriptorMapper::getInstance();
    $rubMap = RubricMapper::getInstance();

    $descriptorIds = [];

    foreach ($_POST['rubric'] as $criterionId => $levelId) {
        // do this the least expensive way Mappers make possible (a custom query would improve this... just saying...)
        $descriptorIds[] = $desMap->findIdsByColumnList([
            'submission_box_id' => $submissionBox->getId(),
            'criterion_id'      => $criterionId,
            'level_id'          => $levelId
        ])[0];
    }

    $grid = $rubMap->findBySubmissionBox($submissionBox);
    $rubMap->applyMark($grid, $descriptorIds);

} else {
    // no rubric
    $grid = null;
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
        $submissionMapper = new \Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
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
    $grid,
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
