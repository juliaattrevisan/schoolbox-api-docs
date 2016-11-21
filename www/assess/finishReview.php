<?php
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
    error(112);
}

if (!is_numeric($this->arg[1])) {
    error(118);
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
	error(112);
	exit;
}

try {
	/** @var QuizAttempt $attempt */
	$attempt = $attemptMapper->findById($this->arg[1]);
}
catch(\Schoolbox\Mapper\Exception $e) {
	error(118);
	exit;
}

if($assess->getUser()->getId() != $sessionUser->getId() && $groupHandler->getPermission($assess->getCategory()->getId()) < WRITE_ACCESS) {
	error(127);
}

$attempt->setReviewed(new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
$attemptMapper->save($attempt);

// TODO someone please tell me the alternative -_-'
$assessmentId = Database::getInstance()->query("SELECT assessment_id FROM assess WHERE assess_id = ? LIMIT 1", [ $assess->getId() ])->fetchColumn();

$this->localRedir($this->routeLookup('learningAssessment.getStatus', [ 'assessmentId' => $assessmentId ]));

die();

