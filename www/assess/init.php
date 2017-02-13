<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Permission\Access\UserAccess;

$assessHandler = new Assess_Main();
$attemptHandler = new Assess_Attempt();
$groupHandler = new Group();
$userHandler = new User();

/**
 * @var SitePage $this
 * @var AssessmentDataSource $assessmentDataSource
 */
$assessmentDataSource = $this->getDi()->get('assessmentDataSource');

if (!is_numeric($this->arg[0])) {
	error(112);
}

if (!$assessHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

/* Get intro summary for the assessment
 */
$assess = $assessHandler->getIntro($this->arg[0]);

if (!$assess) {
	error(112);
}

if ($assessHandler->isDeleted($assess['assess_id'])) {
	error(112);
}

/* Check if you are in this category, so you can't submit other category's
 * online assessments unless you are a part of that category.
 */
$assessment = $assessmentDataSource->findByQuizId($assess['assess_id'], $_SESSION['user']);
if (!$assessment->isParticipant($_SESSION['user']) && $groupHandler->getPermission($assess['category_id']) < UserAccess::ACCESS_WRITE) {
	error(19);
}

/* Check if this assessment can be started
 */
if ($assessHandler->isExpired($assess['assess_id'])) {
	error(120);
}

/* Participants should be blocked from attempting if it hasn't started,
 * but authors should be able to test out their content
 */
if (!$assessHandler->isOpen($assess) && ($groupHandler->getPermission($assess['category_id']) < UserAccess::ACCESS_WRITE)) {
	error(143);
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
	if (($assess['max_attempts'] > 0) && ($numAttempts + 1 > $assess['max_attempts'])) {
		/* Clear and exit when you attempt to make more than is allowed
		 */
		error(146);
	}
	else {
		$attemptId = $attemptHandler->start($userId, $this->arg[0]);
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
                $pool = 0;
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
