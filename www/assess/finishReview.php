<?php
use Schoolbox\Assessment\Component\OnlineAssessment\AttemptMapper;
use Schoolbox\Learning\Quiz\Mapper;
use Schoolbox\Learning\Quiz\Model;

/**
 * Review completion. Marks when an assessment review is finished.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
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

$assessMapper = Mapper::getInstance();
$attemptMapper = AttemptMapper::getInstance();

try {
	/** @var Model $assess */
	$assess = $assessMapper->findById($this->arg[0]);
}
catch(\Schoolbox\Mapper\Exception $e) {
	error(112);
	exit;
}

try {
	/** @var \Schoolbox\Assessment\Component\OnlineAssessment\Attempt $attempt */
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

SitePage::localRedir('/assess/results/' . $this->arg[0]);

die();

