<?php
/**
 * Saves a Response to a Question.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

$assessHandler = new Assess_Main();
$attemptHandler = new Assess_Attempt();
$questionHandler = new Assess_Question();
$sectionHandler = new Assess_Section();

$question = $questionHandler->getByID($this->arg[0]);
$section = $sectionHandler->getByID($question['section_id']);

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
$accessLevel      = Access::getInstance()->getFolderAccess($_SESSION['user'], [$assessmentFolder->getId()]);

if ($assessHandler->isExpired($section['assess_id'])) {
    // If I'm an instructor, I should be allowed to attempt anything that is in a course spec.
    if (!($accessLevel >= UserAccess::ACCESS_WRITE && $assessment->isSpecification())) {
        error(120);
    }
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

if ($accessLevel < UserAccess::ACCESS_WRITE && !$assessHandler->isOpen($assess)) {
	error(143);
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

