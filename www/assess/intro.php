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

use Schoolbox\Breadcrumbs;
use Schoolbox\UserMapper;

$assessHandler = new Assess_Main();
$sectionHandler = new Assess_Section();
$attemptHandler = new Assess_Attempt();
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

if ($assessHandler->isDeleted($assess['assess_id'])) {
    error(112);
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
    error(143);
}

/* Check if you have completed an assessment already
 *
 * Set up Attempt vars
 */
$numAttempts = $attemptHandler->userHasAttempted($assess['assess_id'], $_SESSION['id']);

$attempt = $attemptHandler->getLatestResumableForUser($assess['assess_id'], $_SESSION['id']);
$attemptStartTime = strtotime($attempt['start']);
$attemptTimeRemaining = $attemptStartTime + $assess['duration'] - time();
$attemptEndTime = $attemptStartTime + $assess['duration'];

/* If there is a resumable attempt, resume it.
 */
if ($attempt) {
    $_SESSION['attempt_id'] = $attempt['attempt_id'];
}
/* Otherwise start a new attempt if there is a limit
 */
else {

    /* Clear and exit when you attempt to make more than is allowed
      */
    $_SESSION['attempt_id'] = '';

    if (($assess['max_attempts'] > 0) && ($numAttempts + 1 > $assess['max_attempts'])) {
        error(146);
    }
}

/* Check if Attempt ran out of time
 * If it did, give the student another attempt, if they have one.
 */
if (($attemptTimeRemaining < 0) && ($attemptStartTime > 0) && ($assess['duration'] != Assess_Main::UNLIMITED_DURATION)) {
    // Close off the last attempt first (at the time it would have ended naturally)
    $closeTime = new Alaress_Date_Format($attemptEndTime, 'U');
    $attemptHandler->update(array(
        'attempt_id' => $attempt['attempt_id'],
        'finish'     => $closeTime->getDatabase(),
    ));
    $numAttempts++;
    if (($assess['max_attempts'] > 0) && ($numAttempts > $assess['max_attempts'])) {
        error(122);
    }
}

/* Continue once you get past checks
 */
$author = UserMapper::getInstance()->findById($assess['user_id']);
$folder = Schoolbox\Resource\Folder\Mapper::getInstance()->findById($assess['category_id']);

$allSummary = $assessHandler->getIntroSummary($assess['assess_id']);
$allSections = $sectionHandler->getList($assess['assess_id']);

$assessStartDate = new Alaress_Date_Format(strtotime($assess['start']), 'U');
$assessFinishDate = new Alaress_Date_Format(strtotime($assess['finish']), 'U');

$this->setFile(TEMPLATE_content, 'assess/intro.html');
$this->setBlock(TEMPLATE_content, 'section', 'section_block');
$this->setBlock(TEMPLATE_content, 'startTest', 'startTest_block');
$this->setTitle('Assessment: ' . $assess['title']);

$this->setVar(array(
    'ASSESS_ASSESS_ID' => $assess['assess_id'],
    'ASSESS_TITLE' => Scrub::text($assess['title']),
    'ASSESS_DESCRIPTION' => Scrub::html($assess['description']),
    'ASSESS_START' => $assessStartDate->getShort(),
    'ASSESS_FINISH' => $assessFinishDate->getShort(),
    'FIRST_SECTION' => $allSections[0]['section_id'],
    'ASSESS_AUTHOR' => Scrub::text($author->formatName()),
    'ASSESS_AUTHOR_ID' => $author->getId(),
    'ASSESS_AUTHOR_EXT_ID' => $author->getExternalId(),
    'ASSESS_AUTHOR_ROLE' => $author->getRole()->getName(),
    'ASSESS_FOLDER' => $folder->getName(),
    'ASSESS_FOLDER_ID' => $folder->getId(),
    'ASSESS_FOLDER_CODES' => $folder->getCodeString() ?: '',
    'ASSESS_DURATION' => $assess['duration'] == Assess_Main::UNLIMITED_DURATION ? 'Unlimited' : $assessHandler->sec2hms($assess['duration']),
));

/* Section list and question count
 */
if (is_array($allSummary) && count($allSummary)) {
    foreach ($allSummary as $currentSummary) {
        $this->setVar(array(
            'SECTION_SEQUENCE' => $currentSummary['sequence'],
            'SECTION_TITLE' => Scrub::text($currentSummary['title']),
            'SECTION_NUMQUESTIONS' => $currentSummary['pool'] > 0 ? $currentSummary['pool'] : $currentSummary['numQuestions'],
        ));

        $this->parse('section_block', 'section', 'true');
    }

    $this->parse('startTest_block', 'startTest');
} else {
    $this->setVar('section_block', '');
    $this->setVar('startTest_block', '');
}

$trail = new Breadcrumbs();
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text'  => 'Work',
        'href'  => '/submission/allWork/' . $_SESSION['id'],
        'class' => ''
    ],
    [
        'text'  => $folder->getName(),
        'href'  => sprintf('/submission/allWork/%d/%d/%d/%d', $_SESSION['id'], date('Y', strtotime($assess['finish'])), date('m', strtotime($assess['finish'])) > 6 ? 2 : 1, $folder->getId()),
        'class' => ''
    ],
    [
        'text'  => $assess['title'],
        'href'  => '/assess/intro/' . $assess['assess_id'],
        'class' => 'active'
    ],
]);
