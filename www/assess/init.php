<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Error;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Quiz;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;

$assessHandler = new Assess_Main();
$attemptHandler = new Assess_Attempt();
$userHandler = new User();
$quizId = $this->arg[0];

/**
 * @var SitePage $this
 * @var AssessmentDataSource $assessmentDataSource
 */
$assessmentDataSource = $this->getDi()->get('assessmentDataSource');

if (!is_numeric($quizId)) {
	error(112);
}

if (!$assessHandler->isValidUrlAccess($quizId)) {
    $this->pageNotFound();
}

/* Get intro summary for the assessment
 */
$assess = $assessHandler->getIntro($quizId);

if (!$assess) {
	error(112);
}

if ($assessHandler->isDeleted($assess['assess_id'])) {
	error(112);
}

/* Check the folder exists meaningfully
 */
try {
    $folder = Folder\Mapper::getInstance()->findById($assess['category_id']);
}
catch (Mapper\Exception $e) {
    error(Error\Mapper::ERROR_SELECT_QUIZ_GROUP);
}

/* Check if you are in this category, so you can't submit other category's
 * online assessments unless you are a part of that category.
 */
$assessment       = $assessmentDataSource->findByQuizId($assess['assess_id'], $_SESSION['user']);
$assessmentFolder = $assessment->getBaseContext()->getFolder();
$accessLevel      = Access::getInstance()->getFolderAccess($_SESSION['user'], $assessmentFolder->getId());

/* Non-participants are sent to a 404 screen
 */
$actionValidator = new Quiz\ActionValidator(Alaress_Date_Format::now(), Access::getInstance());
if (!$actionValidator->canAttemptQuiz($_SESSION['user'], $assessment)) {
    $this->pageNotFound();
}

if ($accessLevel < UserAccess::ACCESS_WRITE) {
    // I can't attempt a spec assessment
    if ($assessment->isSpecification()) {
        error(19);
    }
} else {
    // I can do whatever I want
}

/* Check if you have completed an assessment already
 *
 * Set up Attempt vars
 */
$userId      = $_SESSION['user']->getId();
$numAttempts = $attemptHandler->userHasAttempted($assess['assess_id'], $userId);

$attempt = $attemptHandler->getLatestResumableForUser($assess['assess_id'], $userId);

/* If there is a resumable attempt, resume it.
 * Otherwise start a new attempt if there is a limit
 */
if (!$attempt) {
    /* Clear and exit when you attempt to make more than is allowed
     * (Teachers may preview this quiz as many attempts as they want)
     */
    if ($accessLevel < UserAccess::ACCESS_WRITE
        && ($assess['max_attempts'] > 0)
        && ($numAttempts + 1 > $assess['max_attempts'])
    ) {
        error(146);
    } else {
        $quizModel     = Quiz\Mapper::getInstance()->createModel($quizId);
        $attemptMapper = Quiz\Attempt\Mapper::getInstance();
        $attemptModel  = $attemptMapper->createModel()
                                       ->setQuiz($quizModel)
                                       ->setUser(UserMapper::getInstance()->createModel($userId))
                                       ->setStart(Alaress_Date_Format::now());
        $attemptMapper->save($attemptModel);
        $quizModel->setAttempts(new GenericCollection(Quiz\Attempt\Model::class, [$attemptModel]));
        $attemptId = $attemptModel->getId();
        // Assign all questions on quiz init, not starting a section.
        // This ensures that a response is created for all answers,
        // so they contribute to total mark. (SB-7928)
        $responseHandler = new Assess_Response();
        $sectionHandler  = new Assess_Section();
        foreach ($sectionHandler->getList($assess['assess_id']) as $section) {
            if ($section['random']) {
                $pool = $section['pool'];
                $randomise = Assess_Response::RANDOMISE_QUESTIONS;
            } else {
                $pool = Assess_Section::POOL_SIZE_ALL;
                $randomise = Assess_Response::ORDERED_QUESTIONS;
            }
            $responseHandler->assignQuestions(
                $attemptId,
                $section['section_id'],
                $pool,
                $randomise
            );
        }
	}
}

/* Redirect to the original destination
 */

$returnUrlSuffix = isset($_GET['returnUrl']) ? "?returnUrl=". $_GET['returnUrl'] : '';

SitePage::localRedir('/assess/attempt/' . $this->arg[0] . '/' . $this->arg[1] . $returnUrlSuffix);
