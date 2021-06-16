<?php

/**
 * Saves a Reviewed Response to a Question. This is where the assessor can
 * change the mark.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Error;
use Schoolbox\Learning\Quiz\ActionValidator;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;

$assessHandler = new Assess_Main();
$questionHandler = new Assess_Question();
$sectionHandler = new Assess_Section();
$groupHandler = new Group();
$attemptHandler = new Assess_Attempt();
$responseHandler = new Assess_Response();
$question = $questionHandler->getByID($this->arg[0]);
$section = $sectionHandler->getByID($question['section_id']);
$assess = $assessHandler->getByID($section['assess_id']);
if (!$assessHandler->isValidUrlAccess($section['assess_id'])) {
    $this->pageNotFound();
}

if ($assessHandler->isDeleted($section['assess_id'])) {
    error(Error\Mapper::ERROR_QUIZ_NOT_FOUND);
    exit;
}

if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < UserAccess::ACCESS_WRITE)) {
    error(Error\Mapper::ERROR_PERM_TEST_REVIEW);
    exit;
}

if (!$question) {
    error(Error\Mapper::ERROR_QUESTION_NOT_FOUND);
    exit;
}

/* Check the folder exists meaningfully
 */
try {
    $folder = Folder\Mapper::getInstance()->findById($assess['category_id']);
} catch (Mapper\Exception $e) {
    error(Error\Mapper::ERROR_SELECT_QUIZ_GROUP);
    exit;
}

$response = $responseHandler->getByID($this->arg[1]);
$attempt = $attemptHandler->getByID($response['attempt_id']);
try {
    $user = UserMapper::getInstance()->findById($attempt['user_id']);
} catch (Mapper\Exception $e) {
    error(Error\Mapper::ERROR_USER_NOT_FOUND);
    exit;
}

/* Excluded participants 404
 */
$actionValidator = new ActionValidator(\Alaress_Date_Format::now(), Access::getInstance());
if (!$actionValidator->canReviewAttempts($_SESSION['user'], $user, $this->getDi()->get('assessmentDataSource')->findByQuizId($assess['assess_id'], $user))) {
    $this->pageNotFound();
}

$factory = new Assess_Factory();
$answerHandler = $factory->createAnswerModel($question['type_id']);
$mark = filter_var($_POST['reviewedMarks'] ?? '', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
if (!isset($mark)) {
    // Generic "not enough submitted" message
    error(Error\Mapper::ERROR_INFORMATION_MISSING);
    exit;
}
$result = $answerHandler->saveReviewedResponse($_POST, $this->arg[1]);
echo json_encode($result);
/* Prevent rendering of template
 */
exit;
