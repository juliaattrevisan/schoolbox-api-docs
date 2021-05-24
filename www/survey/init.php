<?php

/**
 * Initialise a survey
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

$surveyHandler   = new Survey_Main();
$sectionHandler  = new Survey_Section();
$questionHandler = new Survey_Question();
$attemptHandler  = new Survey_Attempt();
$groupHandler    = new Group();

if (!is_numeric($this->arg[0])) {
    error(112);
}

if (!$surveyHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

if (!is_numeric($this->arg[1])) {
    error(115);
}

$survey = $surveyHandler->getByID($this->arg[0]);
$section = $sectionHandler->getByID($this->arg[1]);

if ($surveyHandler->isDeleted($survey['assess_id'])) {
    error(112);
}

if (!$section) {
    error(115);
}

/* Check if you are in this category, so you can't submit other category's
 * surveys unless you are a part of that category.
 */
if (!$surveyHandler->canDoAssessment($survey['assess_id'])) {
    error(19);
}

/* Check if this assessment can be started
 */
if ($surveyHandler->isExpired($survey['assess_id'])) {
    error(120);
}

/* Participants should be blocked from attempting if it hasn't started,
 * but authors should be able to test out their content
 */
if (!$surveyHandler->isOpen($survey) && ($groupHandler->getPermission($survey['category_id']) < UserAccess::ACCESS_WRITE)) {
    error(144);
}

if (!$questionHandler->numQuestions($survey['assess_id'])) {
    error(137);
}

/* Disabled: doing so will allow teachers to give their test a "dry run" and
 * they can delete their attempts afterwards

if ($surveyHandler->isOwner($this->arg[0])) {
    error(124);
}
*/

/* Check if you have completed an assessment already
 *
 * Set up Attempt vars
 */
$userId      = $_SESSION['user']->getId();
$numAttempts = $attemptHandler->userHasAttempted($survey['assess_id'], $userId);

$attempt = $attemptHandler->getLatestResumableForUser($survey['assess_id'], $userId);

/* If there is a resumable attempt, resume it.
 * Otherwise start a new attempt if there is a limit
 */
if (!$attempt) {
    if (($survey['max_attempts'] > 0) && ($numAttempts + 1 > $survey['max_attempts'])) {
        /* Clear and exit when you attempt to make more than is allowed
         */
        error(146);
    } else {
        $attemptId = $attemptHandler->start($userId, $this->arg[0]);
        // Assign all questions on init, not starting a section.
        $responseHandler = new Survey_Response();
        $sectionHandler  = new Survey_Section();
        foreach ($sectionHandler->getList($survey['assess_id']) as $section) {
            // Randomized questions aren't available for surveys
            $responseHandler->assignQuestions(
                $attemptId,
                $section['section_id'],
                $pool = Survey_Section::POOL_SIZE_ALL,
                false
            );
        }
    }
}

/* Redirect to the original destination
 */
SitePage::localRedir('/survey/attempt/' . $this->arg[0] . '/' . $this->arg[1]);
