<?php

$this->pageNotFound();

///**
// * Review completion. Marks when an assessment review is finished.
// *
// * @author      Steve Chan <steve.chan@alaress.com.au>
// * @copyright   Copyright 2019, Schoolbox Pty Ltd
// * @link        https://schoolbox.com.au/
// */
//
//use Schoolbox\Learning\Assessment\Project;
//use Schoolbox\Learning\Quiz\ActionValidator;
//use Schoolbox\Learning\Quiz\Attempt\Model as QuizAttempt;
//use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
//use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
//use Schoolbox\Learning\Quiz\Model as Quiz;
//use Schoolbox\Error;
//use Schoolbox\Permission\Access\Access;
//use Schoolbox\Permission\Access\UserAccess;
//use Schoolbox\Learning\Assessment\DataModel as Assessment;
//
///**
// * u r in a sitepage
// *
// * @var SitePage $this
// */
//
//$assessHandler = new Assess_Main();
//$attemptHandler = false;
//$groupHandler = new Group();
///** @var \Schoolbox\User $sessionUser */
//$sessionUser = $_SESSION['user'];
//if (!is_numeric($this->arg[0])) {
//    error(Error\Mapper::ERROR_QUIZ_NOT_FOUND);
//}
//
//if (!is_numeric($this->arg[1])) {
//    error(Error\Mapper::ERROR_ATTEMPT_NOT_FOUND);
//}
//
//if (!$assessHandler->isValidUrlAccess($this->arg[0])) {
//    $this->pageNotFound();
//}
//
//$assessMapper = QuizMapper::getInstance();
//$attemptMapper = QuizAttemptMapper::getInstance();
//try {
///** @var Quiz $assess */
//    $assess = $assessMapper->findById($this->arg[0]);
//} catch (\Schoolbox\Mapper\Exception $e) {
//    error(Error\Mapper::ERROR_QUIZ_NOT_FOUND);
//    exit;
//}
//
//try {
///** @var QuizAttempt $attempt */
//    $attempt = $attemptMapper->findById($this->arg[1]);
//} catch (\Schoolbox\Mapper\Exception $e) {
//    error(Error\Mapper::ERROR_ATTEMPT_NOT_FOUND);
//    exit;
//}
//
///** @var Assessment\MapperProxy $assessmentMapperProxy */
//$assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');
//$quiz                  = $assessmentMapperProxy->findByQuizId($assess->getId());
//$actionValidator       = new ActionValidator(Alaress_Date_Format::now(), Access::getInstance());
//if (!$actionValidator->canReviewAttempts($sessionUser, $attempt->getUser(), $quiz)) {
//    $this->pageNotFound();
//}
//
//
//if ($assess->getUser()->getId() != $sessionUser->getId() && $groupHandler->getPermission($assess->getCategory()->getId()) < UserAccess::ACCESS_WRITE) {
//    error(Error\Mapper::ERROR_PERM_TEST_REVIEW);
//}
//
//$attemptMapper->finalizeReview($attempt, $sessionUser);
//
///* Potentially invalidates a project result cache since a Quiz Feedback
// * changes a project's outcome; update the cache accordingly
// *
// * Specifically avoid mapper cache, otherwise it knows all the attempts
// * except the one you just finalised and summates those, which isn't what
// * we want. We need the latest.
// */
//if ($quiz->getContext() instanceof Project) {
//     (new Project\ResultCache\ResultCache(Project\ResultCache\Mapper::getInstance()))->refresh($assessmentMapperProxy, $quiz, $attempt->getUser());
//}
//
//$assessmentId = $assess->getAssessmentModel()->getId();
//$this->localRedir($this->routeLookup('learningAssessment.getStatus', [ 'assessmentId' => $assessmentId ]));
//die();
