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
use Schoolbox\Assessment\Participant;
use Schoolbox\Error;
use Schoolbox\Learning\Quiz\ValidParticipant;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;

$attemptsToDelete = [];

$assessHandler   = new Assess_Main();
$attemptHandler  = new Assess_Attempt();
$responseHandler = new Assess_Response();
$groupHandler    = new Group();


$getAttempt = function ($attemptId) use ($attemptHandler, $assessHandler, $groupHandler) {
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
    }

    /* Check the folder exists meaningfully
     */
    try {
        $folder = Folder\Mapper::getInstance()->findById($assess['category_id']);
    }
    catch (Mapper\Exception $e) {
        error(Error\Mapper::ERROR_SELECT_QUIZ_GROUP);
    }

    /* Non-participants are sent to a 404 screen
     */
    $isValidParticipant = new ValidParticipant(Participant\Plan\Mapper::getInstance(), Participant\Mapper::getInstance());

    if (!$isValidParticipant->isSatisfiedBy($user, $folder, $assess['assessment_id'])) {
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
