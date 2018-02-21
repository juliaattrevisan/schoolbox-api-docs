<?php
/**
 * Assessment Attempt deletion. Responses are all softdeleted if
 * the Attempt is softdeleted.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

/* Get a list of questions and answers to softdelete
 */

/* Check that you can delete these attempts first
 */
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Quiz\ActionValidator;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;

$attemptsToDelete = [];

/** @var Assessment\MapperProxy $assessmentMapperProxy */
$assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');
$assessHandler   = new Assess_Main();
$attemptHandler  = new Assess_Attempt();
$responseHandler = new Assess_Response();
$groupHandler    = new Group();


$getAttempt = function ($attemptId) use ($assessmentMapperProxy, $attemptHandler, $assessHandler, $groupHandler) {
    if (!is_numeric($attemptId)) {
        throw \Schoolbox\Error\Mapper::getInstance()->generateException(Schoolbox\Error\Mapper::ERROR_PERM_ATTEMPT_DELETE);
    }

    $attempt = $attemptHandler->getByID($attemptId);

    if (!$assessHandler->isValidUrlAccess($attempt['assess_id'])) {
        throw new NotFound();
    }

    $assess = $assessHandler->getByID($attempt['assess_id']);

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
        throw Schoolbox\Error\Mapper::getInstance()->generateException(Schoolbox\Error\Mapper::ERROR_PERM_ATTEMPT_DELETE);
    }

    try {
        $user = UserMapper::getInstance()->findById($attempt['user_id']);
    }
    catch (Mapper\Exception $e) {
        error(Error\Mapper::ERROR_USER_NOT_FOUND);
        die();
    }

    /* Check the folder exists meaningfully
     */
    try {
        Folder\Mapper::getInstance()->findById($assess['category_id']);
    }
    catch (Mapper\Exception $e) {
        error(Error\Mapper::ERROR_SELECT_QUIZ_GROUP);
    }

    // It appears this controller can be called with unrelated attemptIds, so need to load each attempt's quiz.
    $assessment      = $assessmentMapperProxy->findByQuizId($assess['assess_id'], $user);
    $actionValidator = new ActionValidator(Alaress_Date_Format::now(), $assessment, Access::getInstance());
    if (!$actionValidator->canDeleteAttempts($_SESSION['user'], $user)) {
        $this->pageNotFound();
    }

    return $attempt;
};

$deleteAttempt = function (array $attempt) use ($responseHandler, $attemptHandler) {
    $responseHandler->deleteResponsesForAttempt($attempt['attempt_id']);
    $attemptHandler->update(array(
        'attempt_id' => $attempt['attempt_id'],
        'deleted_at' => 'NOW()'
    ));

};


if (isset($_POST['userIds']) && isset($_POST['assessmentId'])) {

    $quiz = Schoolbox\Learning\Quiz\Mapper::getInstance()->findOneByColumn('assessment_id', $_POST['assessmentId']);

    $userAttemptIds = Schoolbox\Learning\Quiz\Attempt\Mapper::getInstance()->findIdsByColumnList([
        'user_id'     => $_POST['userIds'],
        'assess_id'   => $quiz->getId(),
        'reviewed_at' => null
    ]);

    foreach ($userAttemptIds as $attemptId) {
        $attemptsToDelete[] = $getAttempt($attemptId);
    }
}

foreach ($_POST['attemptIds'] as $attemptId) {
    $attemptsToDelete[] = $getAttempt($attemptId);
}

/* Multiple soft-delete
 */
foreach ($attemptsToDelete as $currentAttempt) {
    $deleteAttempt($currentAttempt);
}

SitePage::backToTheFuture(1);
