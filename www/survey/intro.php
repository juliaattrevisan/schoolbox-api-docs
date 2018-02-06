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

$surveyHandler = new Survey_Main();
$sectionHandler = new Survey_Section();
$attemptHandler = new Survey_Attempt();
$groupHandler = new Group();
$userHandler = new User();

if (!is_numeric($this->arg[0])) {
    error(112);
}

if (!$surveyHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

/* Get intro summary for the assessment
 */
$survey = $surveyHandler->getIntro($this->arg[0]);


if ($surveyHandler->isDeleted($this->arg[0])) {
    error(112);
}

/* Check if you are in this category, so you can't submit other category's
 * online assessments unless you are a part of that category.
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
if (!$surveyHandler->isOpen($survey) && ($groupHandler->getPermission($survey['category_id']) < WRITE_ACCESS)) {
    error(144);
}

/* Check if you have completed an assessment already
 *
 * Set up Attempt vars
 */
$numAttempts = $attemptHandler->userHasAttempted($survey['assess_id'], $_SESSION['id']);

$attempt = $attemptHandler->getLatestResumableForUser($survey['assess_id'], $_SESSION['id']);
$attemptStartTime = strtotime($attempt['start']);
$attemptTimeRemaining = $attemptStartTime + $survey['duration'] - time();
$attemptEndTime = $attemptStartTime + $survey['duration'];

/* If there is a resumable attempt, resume it.
 * Otherwise start a new attempt if there is a limit
 */
if (!$attempt) {
    /* Clear and exit when you attempt to make more than is allowed
      */
    if (($survey['max_attempts'] > 0) && ($numAttempts + 1 > $survey['max_attempts'])) {
        error(146);
    }
}

/* Check if Attempt ran out of time
 */
if (($attemptTimeRemaining < 0) && ($attemptStartTime > 0) && ($survey['duration'] != Assess_Main::UNLIMITED_DURATION)) {
    error(122);
}

/* Continue once you get past checks
 */
$author = UserMapper::getInstance()->findByIdWithFallback($survey['user_id']);
$folder = Schoolbox\Resource\Folder\Mapper::getInstance()->findById($survey['category_id']);

$allSummary = $surveyHandler->getIntroSummary($survey['assess_id']);
$allSections = $sectionHandler->getList($survey['assess_id']);

$assessStartDate = new Alaress_Date_Format(strtotime($survey['start']), 'U');
$assessFinishDate = new Alaress_Date_Format(strtotime($survey['finish']), 'U');

$this->setFile(TEMPLATE_content, 'survey/intro.html');
$this->setBlock(TEMPLATE_content, 'section', 'section_block');
$this->setBlock(TEMPLATE_content, 'startTest', 'startTest_block');
$this->setBlock(TEMPLATE_content, 'has_assess_description', 'has_assess_description_block');
$this->setTitle('Assessment: ' . Scrub::text($survey['title']));

$this->setVar(array(
    'ASSESS_ASSESS_ID' => $survey['assess_id'],
    'ASSESS_TITLE' => Scrub::text($survey['title']),
    'ASSESS_DESCRIPTION' => Scrub::html($survey['description']),
    'ASSESS_START' => $assessStartDate->getShort(),
    'ASSESS_FINISH' => $assessFinishDate->getShort(),
    'FIRST_SECTION' => $allSections[0]['section_id'],
    'ASSESS_AUTHOR' => Scrub::text($author->formatName()),
    'ASSESS_AUTHOR_ID' => $author->getId(),
    'ASSESS_AUTHOR_EXT_ID' => $author->getExternalId(),
    'ASSESS_AUTHOR_ROLE' => $author->getRole()->getName(),
    'ASSESS_FOLDER' => Scrub::text($folder->getName() . ( $folder->getCodeString() ? ' ('.$folder->getCodeString().')': '')),
    'ASSESS_FOLDER_ID' => $folder->getId(),
    'ASSESS_DURATION' => $survey['duration'] == Survey_Main::UNLIMITED_DURATION ? 'Unlimited' : $surveyHandler->sec2hms($survey['duration']),
));

$survey['description'] = trim($survey['description']);

if (empty($survey['description'])) {
    $this->setVar('has_assess_description_block', '');
} else {
    $this->parse('has_assess_description_block', 'has_assess_description', true);
}

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
        'text'  => $folder->getName(),
        'href'  => '/homepage/' . $folder->getId(),
        'class' => ''
    ],
    [
        'text'  => $survey['title'],
        'href'  => '/survey/intro/' . $survey['assess_id'],
        'class' => 'active'
    ],
]);
