<?php

/**
 * Saves a Response to a Question.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

$surveyHandler   = new Survey_Main();
$attemptHandler  = new Survey_Attempt();
$questionHandler = new Survey_Question();
$sectionHandler  = new Survey_Section();
$groupHandler    = new Group();

$question = $questionHandler->getByID($this->arg[0]);
$section = $sectionHandler->getByID($question['section_id']);

/* TODO: Check if this test is for your category
 */

/* Disabled: authors can try their own surveys
if ($surveyHandler->isOwner($section['assess_id'])) {
    error(124);
}
*/

if ($surveyHandler->isExpired($section['assess_id'])) {
    error(120);
}

if ($surveyHandler->isDeleted($section['assess_id'])) {
    error(112);
}

if (!$surveyHandler->isValidUrlAccess($section['assess_id'])) {
    $this->pageNotFound();
}

/* Participants should be blocked from attempting if it hasn't started,
 * but authors should be able to test out their content
 */
$survey = $surveyHandler->getByID($section['assess_id']);

if (!$surveyHandler->isOpen($survey) && ($groupHandler->getPermission($survey['category_id']) < UserAccess::ACCESS_WRITE)) {
    error(144);
}

if (!$question) {
    error(119);
}

$factory = new Survey_Factory();
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
