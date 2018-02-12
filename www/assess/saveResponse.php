<?php
/**
 * Saves a Response to a Question.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

use Schoolbox\Assessment\Participant;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Quiz\ValidParticipant;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

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

$assessment       = $assessmentDataSource->findByQuizId($section['assess_id'], $_SESSION['user']);
$assessmentFolder = $assessment->getBaseContext()->getFolder();
$accessLevel      = Access::getInstance()->getFolderAccess($_SESSION['user'], $assessmentFolder->getId());

/* Non-participants are sent to a 404 screen
 */
$isValidParticipant = new ValidParticipant(Participant\Plan\Mapper::getInstance(), Participant\Mapper::getInstance());

if (!$isValidParticipant->isSatisfiedBy($_SESSION['user'], $assessmentFolder, $assess['assessment_id'])) {
    $this->pageNotFound();
}

if ($accessLevel < UserAccess::ACCESS_WRITE) {
    // I can't attempt a spec assessment
    if ($assessment->isSpecification()) {
        error(19);
    }

    // I can't do this if not a participant
    if (!$assessment->isParticipant($_SESSION['user'])) {
        error(19);
    }

    // I can't attempt this if it has expired
    if ($assessHandler->isExpired($assess['assess_id'])) {
        error(120);
    }

    // I can't attempt this if it isn't open
    if (!$assessHandler->isOpen($assess)) {
        error(143);
    }

} else {
    // I can do whatever I want
}

if ($assessHandler->isDeleted($section['assess_id'])) {
	error(112);
}

/* Participants should be blocked from attempting if it hasn't started,
 * but authors should be able to test out their content
 */
$assess = $assessHandler->getByID($section['assess_id']);

if (!$assessHandler->isValidUrlAccess($section['assess_id'])) {
    $this->pageNotFound();
}

if (!$question) {
    error(119);
}

$factory = new Assess_Factory();
$answerHandler = $factory->createAnswerModel($question['type_id']);

$data = $_POST;
$data['section_id'] = $question['section_id'];
$data['question_id'] = $this->arg[0];

if($attemptHandler->checkAttemptIsValid($section['assess_id'], $_SESSION['user']->getId(), $data['attempt_id'])) {

    $result = $answerHandler->saveResponse($data);

    /* Find out how many questions were completed in this section already
     */
    $result['percentageComplete'] = $sectionHandler->sectionPercentageComplete($question['section_id'], $data['attempt_id']);

    echo json_encode($result);
}
else {
    http_response_code(403);
}

/* Prevent rendering of template
 */
exit();

