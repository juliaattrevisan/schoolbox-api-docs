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
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Permission\Access\Access;

$assessHandler = new Assess_Main();
$sectionHandler = new Assess_Section();
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
    // Show quiz preview to someone who's none of above, but has read access to the folder
    $assessmentFolder = $assessment->getBaseContext()->getFolder();
    if (Access::getInstance()->getFolderAccess($_SESSION['user'], [$assessmentFolder->getId()]) == UserAccess::ACCESS_READ) {
        $readOnly = true;
    }
    else {
        error(19);
    }
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
$numAttempts = $attemptHandler->userHasAttempted($assess['assess_id'], $_SESSION['id']);

$attempt = $attemptHandler->getLatestResumableForUser($assess['assess_id'], $_SESSION['id']);
$attemptStartTime = strtotime($attempt['start']);
$attemptTimeRemaining = $attemptStartTime + $assess['duration'] - time();
$attemptEndTime = $attemptStartTime + $assess['duration'];

/* If there is a resumable attempt, resume it.
 * Otherwise start a new attempt if there is a limit
 */
if (!$attempt) {
    /* Clear and exit when you attempt to make more than is allowed
      */
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
$this->setBlock(TEMPLATE_content, 'has_assess_description', 'has_assess_description_block');

$this->setTitle('Assessment: ' . Scrub::text($assess['title']));

$this->setVar(array(
    'RETURN_URL_SUFFIX' => isset($_GET["returnUrl"]) ? "?returnUrl=" . $_GET["returnUrl"] : "",
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
    'ASSESS_FOLDER' => Scrub::text($folder->getName() . ($folder->getCodeString() ? ' ('.$folder->getCodeString().')': '')),
    'ASSESS_FOLDER_ID' => $folder->getId(),
    'ASSESS_DURATION' => $assess['duration'] == Assess_Main::UNLIMITED_DURATION ? 'Unlimited' : $assessHandler->sec2hms($assess['duration']),
    'READ_ONLY' => $readOnly,
));

$assess['description'] = trim($assess['description']);

if (empty($assess['description'])) {
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
    if($readOnly) {
        $this->setVar('startTest_block', '');
    }
} else {
    $this->setVar('section_block', '');
    $this->setVar('startTest_block', '');
}

$trail = new Breadcrumbs();
$term  = Schoolbox\Assessment\Term\Mapper::getInstance()->findForDate(
    new Alaress_Date_Format($assess['finish'], Alaress_Date_Format::DATABASE_FORMAT)
);


if (isset($_GET["returnUrl"])) {
    $url = Redirect::decode($_GET["returnUrl"]);
} else {
    $url = sprintf('/homepage/%d', $folder->getId());
}

$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text'  => 'Learning',
        'href'  => '/learning/classes',
        'class' => ''
    ],
    [
        'text'  => $folder->getName(),
        'href'  => $url,
        'class' => ''
    ],
    [
        'text'  => $assess['title'],
        'href'  => '/assess/intro/' . $assess['assess_id'],
        'class' => 'active'
    ],
]);
