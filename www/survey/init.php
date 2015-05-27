<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
$assessHandler = new Survey_Main();
$sectionHandler = new Survey_Section();
$questionHandler = new Survey_Question();
$attemptHandler = new Survey_Attempt();
$groupHandler = new Group();

if (!is_numeric($this->arg[0])) {
	error(112);
}

if (!is_numeric($this->arg[1])) {
	error(115);
}

$assess = $assessHandler->getByID($this->arg[0]);
$section = $sectionHandler->getByID($this->arg[1]);

if (!$assess) {
	error(112);
}

if ($assessHandler->isDeleted($assess['assess_id'])) {
	error(112);
}

if (!$section) {
	error(115);
}

/* Check if you are in this category, so you can't submit other category's
 * online assessments unless you are a part of that category.
 */
if (!$assessHandler->canDoAssessment($assess['assess_id'])) {
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
if (!$assessHandler->isOpen($assess) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
	error(144);
}

if (!$questionHandler->numQuestions($assess['assess_id'])) {
	error(137);
}

/* Disabled: doing so will allow teachers to give their test a "dry run" and
 * they can delete their attempts afterwards

if ($assessHandler->isOwner($this->arg[0])) {
    error(124);
}
*/

/* Check if you have completed an assessment already
 *
 * Set up Attempt vars
 */
$numAttempts = $attemptHandler->userHasAttempted($assess['assess_id'], $_SESSION['id']);

$attempt = $attemptHandler->getLatestResumableForUser($assess['assess_id'], $_SESSION['id']);

/* If there is a resumable attempt, resume it.
 */
if ($attempt) {
	$_SESSION['attempt_id'] = $attempt['attempt_id'];
}
/* Otherwise start a new attempt if there is a limit
 */
else {
	if (($assess['max_attempts'] > 0) && ($numAttempts + 1 > $assess['max_attempts'])) {

		/* Clear and exit when you attempt to make more than is allowed
		 */
		$_SESSION['attempt_id'] = '';

		error(146);
	}
	else {
		$_SESSION['attempt_id'] = $attemptHandler->start($_SESSION['id'], $this->arg[0]);
        // Assign all questions on init, not starting a section.
        $responseHandler = new Survey_Response();
        $sectionHandler  = new Survey_Section();
        foreach ($sectionHandler->getList($assess['assess_id']) as $section) {
            // Randomized questions aren't available for surveys
            $responseHandler->assignQuestions(
                $_SESSION['attempt_id'],
                $section['section_id'],
                0,
                false
            );
        }
	}
}

/* Redirect to the original destination
 */
SitePage::localRedir('/survey/attempt/' . $this->arg[0] . '/' . $this->arg[1]);