<?php

use Schoolbox\Learning\Quiz\ActionValidator;
use Schoolbox\Learning\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;

/**
 * Review completion. Marks when an assessment review is finished.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

use Schoolbox\Error;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Assessment\DataModel as Assessment;

/**
 * u r in a sitepage
 *
 * @var SitePage $this
 */

$assessHandler = new Assess_Main();
$attemptHandler = false;
$groupHandler = new Group();

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

if (!is_numeric($this->arg[0])) {
    error(Error\Mapper::ERROR_QUIZ_NOT_FOUND);
}

if (!is_numeric($this->arg[1])) {
    error(Error\Mapper::ERROR_ATTEMPT_NOT_FOUND);
}

if (!$assessHandler->isValidUrlAccess($this->arg[0])) {
	$this->pageNotFound();
}

$assessMapper = QuizMapper::getInstance();
$attemptMapper = QuizAttemptMapper::getInstance();

try {
	/** @var Quiz $assess */
	$assess = $assessMapper->findById($this->arg[0]);
}
catch(\Schoolbox\Mapper\Exception $e) {
	error(Error\Mapper::ERROR_QUIZ_NOT_FOUND);
	exit;
}

try {
	/** @var QuizAttempt $attempt */
	$attempt = $attemptMapper->findById($this->arg[1]);
}
catch(\Schoolbox\Mapper\Exception $e) {
	error(Error\Mapper::ERROR_ATTEMPT_NOT_FOUND);
	exit;
}

/** @var Assessment\MapperProxy $assessmentMapperProxy */
$assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');
$quiz                  = $assessmentMapperProxy->findByQuizId($assess->getId());
$actionValidator       = new ActionValidator(Alaress_Date_Format::now(), Access::getInstance());
if (!$actionValidator->canReviewAttempts($sessionUser, $attempt->getUser(), $quiz)) {
    $this->pageNotFound();
}


if($assess->getUser()->getId() != $sessionUser->getId() && $groupHandler->getPermission($assess->getCategory()->getId()) < WRITE_ACCESS) {
	error(Error\Mapper::ERROR_PERM_TEST_REVIEW);
}

$attempt->setReviewed(Alaress_Date_Format::now());
$attempt->setReviewer($sessionUser);
$attemptMapper->save($attempt);

$assessmentId = $assess->getAssessmentModel()->getId();

$this->localRedir($this->routeLookup('learningAssessment.getStatus', [ 'assessmentId' => $assessmentId ]));

die();

