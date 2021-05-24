<?php

/**
 * Saves a Response to a Question.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Quiz\ActionValidator;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Error\Mapper as ErrorMapper;

$assessHandler = new Assess_Main();
$attemptHandler = new Assess_Attempt();
$questionHandler = new Assess_Question();
$sectionHandler = new Assess_Section();

$question = $questionHandler->getByID($this->arg[0]);
$section = $sectionHandler->getByID($question['section_id']);
$assess = $assessHandler->getByID($section['assess_id']);

/* TODO: Check if this test is for your category
 */

/* Disabled: test authors can try their tests and
 * delete their attempts later, as a way of having
 * an interactive preview.

if ($assessHandler->isOwner($section['assess_id'])) {
    error(124);
}

 */

/**
 * @var SitePage $this
 * @var AssessmentDataSource $assessmentDataSource
 */
$assessmentDataSource = $this->getDi()->get('assessmentDataSource');
$ajax = SitePage::detectAjax();

try {
    $assessment = $assessmentDataSource->findByQuizId($section['assess_id'], $_SESSION['user']);
} catch (\Schoolbox\Mapper\Exception\Deleted $e) {
    if ($ajax) {
        http_response_code(404);
        exit;
    }
    $this->error(ErrorMapper::ERROR_QUIZ_NOT_FOUND);
}
$assessmentFolder = $assessment->getBaseContext()->getFolder();
$accessLevel      = Access::getInstance()->getFolderAccess($_SESSION['user'], $assessmentFolder->getId());

$actionValidator = new ActionValidator(Alaress_Date_Format::now(), Access::getInstance());

/* Non-participants are sent to a 404 screen
 */
if (!$actionValidator->canAttemptQuiz($_SESSION['user'], $assessment)) {
    if ($ajax) {
        http_response_code(404);
        exit;
    }
    $this->pageNotFound();
}

if ($accessLevel < UserAccess::ACCESS_WRITE) {
    // I can't attempt a spec assessment
    if ($assessment->isSpecification()) {
        if ($ajax) {
            http_response_code(403);
            exit;
        }
        $this->error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }
} else {
    // I can do whatever I want
}

if ($assessHandler->isDeleted($section['assess_id'])) {
    if ($ajax) {
        http_response_code(404);
        exit;
    }
    $this->error(ErrorMapper::ERROR_QUIZ_NOT_FOUND);
}

/* Participants should be blocked from attempting if it hasn't started,
 * but authors should be able to test out their content
 */
$assess = $assessHandler->getByID($section['assess_id']);

if (!$assessHandler->isValidUrlAccess($section['assess_id'])) {
    if ($ajax) {
        http_response_code(404);
        exit;
    }
    $this->pageNotFound();
}

if (!$question) {
    if ($ajax) {
        http_response_code(404);
        exit;
    }
    $this->error(ErrorMapper::ERROR_QUESTION_NOT_FOUND);
}

$factory = new Assess_Factory();
$answerHandler = $factory->createAnswerModel($question['type_id']);

$data = $_POST;
$data['section_id'] = $question['section_id'];
$data['question_id'] = $this->arg[0];

if ($attemptHandler->checkAttemptIsValid($section['assess_id'], $_SESSION['user']->getId(), $data['attempt_id'])) {
    $result = $answerHandler->saveResponse($data);

    /* Find out how many questions were completed in this section already
     */
    $result['percentageComplete'] = $sectionHandler->sectionPercentageComplete($question['section_id'], $data['attempt_id']);

    echo json_encode($result);
} else {
    http_response_code(403);
}

/* Prevent rendering of template
 */
exit();
