<?php
/**
 * Assessment commencement. If the Assessment allows people to re-sit the test,
 * then clear the current attempt_id. Otherwise, remember it so people can't
 * reset the assessment timer by repeatedly refreshing the intro page.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once('../library/survey/Survey.php');
require_once('../library/survey/SurveySection.php');
require_once('../library/survey/SurveyAttempt.php');
require_once('../library/group.php');
require_once('../library/user.php');

$assessHandler = new Survey();
$sectionHandler = new SurveySection();
$attemptHandler = new SurveyAttempt();
$groupHandler = new Group();
$userHandler = new User();

if (!is_numeric($this->arg[0])) {
    error(112);
}

/* Get intro summary for the assessment
 */
$assess = $assessHandler->getIntro($this->arg[0]);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

/* Check if you are in this category, so you can't submit other category's
 * online assessments unless you are a part of that category.
 */
if (!$assessHandler->canDoAssessment($this->arg[0])) {
    error(19);
}

/* Check if this assessment can be started
 */
if ($assessHandler->isExpired($this->arg[0])) {
    error(120);
}

$author = $userHandler->getByID($assess['user_id']);

$allSummary = $assessHandler->getIntroSummary($this->arg[0]);
$allSections = $sectionHandler->getList($assess['assess_id']);

$assessStartDate = new Alaress_Date_Format(strtotime($assess['start']), 'U');
$assessFinishDate = new Alaress_Date_Format(strtotime($assess['finish']), 'U');

$this->setVar(array(
    'ASSESS_ASSESS_ID' => $this->arg[0],
    'ASSESS_TITLE' => $assess['title'],
    'ASSESS_DESCRIPTION' => $assess['description'],
    'ASSESS_START' => $assessStartDate->getShort(),
    'ASSESS_FINISH' => $assessFinishDate->getShort(),
    'FIRST_SECTION' => $allSections[0]['section_id'],
    'ASSESS_AUTHOR' => $author['firstname'] . ' ' . $author['lastname'],
));

$this->setFile(TEMPLATE_content, 'survey/intro.html');
$this->setBlock(TEMPLATE_content, 'section', 'section_block');
$this->setBlock(TEMPLATE_content, 'startTest', 'startTest_block');
$this->setBlock(TEMPLATE_content, 'error', 'error_block');
$this->setTitle('Assessment: ' . $assess['title']);

$this->setVar(array(
    'ASSESS_DURATION' => $assess['duration'] == Survey::UNLIMITED_DURATION ? 'Unlimited' : $assessHandler->sec2hms($assess['duration']),
));

/* Section list and question count
 */
if (is_array($allSummary) && count($allSummary)) {
    foreach($allSummary as $currentSummary) {
        $this->setVar(array(
            'SECTION_SEQUENCE' => $currentSummary['sequence'],
            'SECTION_TITLE' => $currentSummary['title'],
            'SECTION_NUMQUESTIONS' => $currentSummary['numQuestions'],
        ));

        $this->parse('section_block', 'section', 'true');
    }

    $this->parse('startTest_block', 'startTest');
}
else {
    $this->setVar('section_block', '');
    $this->setVar('startTest_block', '');
}

/* Check if you have completed an assessment already
 *
 * Set up Attempt vars
 */
$attempt = $attemptHandler->getLatestForUser($assess['assess_id'], $_SESSION['id']);
$attemptStartTime = strtotime($attempt['start']);
$attemptTimeRemaining = $attemptStartTime + $assess['duration'] - time();
$attemptEndTime = $attemptStartTime + $assess['duration'];

/* Tests can be undertaken if:
 *
 * 1. The assessment allows resit and the assessment hasn't expired
 * 2. The assessment is one-time-only, and you haven't completed an attempt, and have time to do it, and have not yet attempted
 */
if ($assessHandler->allowResit($this->arg[0])) {
    if (!(($assess['duration'] == Survey::UNLIMITED_DURATION) || (strtotime($assess['finish']) > time()))) {
        $this->setVar(array(
            'ERROR_STRING' => 'This online assessment has expired.'
        ));

        $this->setVar('startTest_block', '');
        $this->parse('error_block', 'error');
    }
    else {
        $_SESSION['attempt_id'] = '';

        $this->setVar('error_block', '');
    }
}
else {
    if ($attemptHandler->isComplete($assess['assess_id'])) {
        $this->setVar(array(
            'ERROR_STRING' => 'You have already attempted this survey and cannot re-sit it.'
        ));

        $this->setVar('startTest_block', '');
        $this->parse('error_block', 'error');
    }
    else if (($attemptTimeRemaining < 0) && ($attemptStartTime > 0) && ($assess['duration'] != Survey::UNLIMITED_DURATION)) {
        $this->setVar(array(
            'ERROR_STRING' => 'You have run out of time to complete this survey and cannot re-sit it.'
        ));

        $this->setVar('startTest_block', '');
        $this->parse('error_block', 'error');
    }
    else if ($assessHandler->isExpired($this->arg[0])) {
        $this->setVar(array(
            'ERROR_STRING' => 'This survey has expired.'
        ));

        $this->setVar('startTest_block', '');
        $this->parse('error_block', 'error');
    }
    else if ($attemptHandler->userHasAttempted($this->arg[0], $_SESSION['id'])) {

        /* Load attempt id so the user can resume
         */
        $latest = $attemptHandler->getLatestForUser($this->arg[0], $_SESSION['id']);

        $_SESSION['attempt_id'] = $latest['attempt_id'];

        $this->setVar('error_block', '');
    }
    else {
        $_SESSION['attempt_id'] = '';

        $this->setVar('error_block', '');
    }
}
?>
